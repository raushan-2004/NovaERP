import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Product } from '../../types/api';

const fields: CrudField[] = [
  { name: 'sku', label: 'SKU / Model Number', type: 'text', required: true, placeholder: 'e.g. IC-NE555-DIP8' },
  { name: 'name', label: 'Product Name', type: 'text', required: true, placeholder: 'e.g. NE555 Timer IC DIP-8' },
  { name: 'barcode', label: 'Barcode (UPC/EAN)', type: 'text', placeholder: 'e.g. 290123456789' },
  { name: 'category_id', label: 'Category', type: 'select', required: true, optionsUrl: 'categories' },
  { name: 'brand_id', label: 'Brand', type: 'select', required: true, optionsUrl: 'brands' },
  { name: 'unit_id', label: 'Unit of Measure', type: 'select', required: true, optionsUrl: 'units' },
  {
    name: 'product_type',
    label: 'Product Type',
    type: 'select',
    required: true,
    options: [
      { value: 'raw_material', label: 'Raw Material' },
      { value: 'semi_finished', label: 'Semi-Finished' },
      { value: 'finished_good', label: 'Finished Good' },
    ],
  },
  { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter product specifications and details...', hideInTable: true },
  {
    name: 'status',
    label: 'Status',
    type: 'select',
    required: true,
    options: [
      { value: 'active', label: 'Active' },
      { value: 'inactive', label: 'Inactive' },
    ],
  },
];

export function ProductsPage() {
  return (
    <CrudPage<Product>
      title="Products"
      endpoint="products"
      fields={fields}
      viewPermission="products.view"
      createPermission="products.create"
      updatePermission="products.update"
      deletePermission="products.delete"
    />
  );
}

export default ProductsPage;
