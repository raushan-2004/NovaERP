<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseRequestService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeries,
        private readonly PurchaseOrderService $poService
    ) {}

    /**
     * Valid transitions:
     *   draft       → submitted
     *   submitted   → approved
     *   submitted   → rejected
     *   approved    → converted  (via convertToPo)
     *   rejected    → (terminal)
     *   converted   → (terminal)
     */
    private const ALLOWED_TRANSITIONS = [
        'draft'     => ['submitted'],
        'submitted' => ['approved', 'rejected'],
        'approved'  => ['converted'],
        'rejected'  => [],
        'converted' => [],
    ];

    public function create(array $data, User $actor): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $actor) {
            $pr = PurchaseRequest::create([
                'request_number' => $this->numberSeries->next('PR'),
                'company_id'     => $data['company_id'],
                'branch_id'      => $data['branch_id'],
                'requested_by'   => $actor->id,
                'required_date'  => $data['required_date'] ?? null,
                'status'         => 'draft',
                'notes'          => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $pr->lines()->create([
                    'product_id' => $line['product_id'],
                    'unit_id'    => $line['unit_id'],
                    'quantity'   => $line['quantity'],
                    'notes'      => $line['notes'] ?? null,
                ]);
            }

            return $pr->load('lines.product', 'lines.unit', 'company', 'branch', 'requestedBy');
        });
    }

    public function submit(PurchaseRequest $pr, User $actor): PurchaseRequest
    {
        return $this->transition($pr, 'submitted');
    }

    public function approve(PurchaseRequest $pr, User $actor): PurchaseRequest
    {
        return $this->transition($pr, 'approved');
    }

    public function reject(PurchaseRequest $pr, User $actor): PurchaseRequest
    {
        return $this->transition($pr, 'rejected');
    }

    public function convertToPo(PurchaseRequest $pr, array $poData, User $actor): PurchaseOrder
    {
        $this->transition($pr, 'converted');

        return $this->poService->createFromPr($pr, $poData, $actor);
    }

    private function transition(PurchaseRequest $pr, string $to): PurchaseRequest
    {
        $from    = $pr->status;
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new InvalidStatusTransitionException('Purchase Request', $from, $to);
        }

        $pr->status = $to;
        $pr->save();

        return $pr->fresh();
    }
}
