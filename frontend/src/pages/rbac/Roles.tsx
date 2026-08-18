import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Role } from '../../types/api';

const fields: CrudField[] = [
  { name: 'name', label: 'Role Name', type: 'text', required: true, placeholder: 'e.g. Inventory Manager' },
  { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Describe the responsibilities of this role...' },
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

export function RolesPage() {
  return (
    <CrudPage<Role>
      title="Roles"
      endpoint="roles"
      fields={fields}
      viewPermission="roles.view"
      createPermission="roles.create"
      updatePermission="roles.update"
      deletePermission="roles.delete"
    />
  );
}

export default RolesPage;
