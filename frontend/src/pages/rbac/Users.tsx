import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { AuthUser } from '../../types/api';

const fields: CrudField[] = [
  { name: 'name', label: 'User Name', type: 'text', required: true, placeholder: 'e.g. Rahul Sharma' },
  { name: 'email', label: 'Email Address', type: 'email', required: true, placeholder: 'e.g. rahul@novatech.com' },
  { name: 'password', label: 'Password', type: 'password', placeholder: 'Enter password (leave blank to keep unchanged)', hideInTable: true },
];

const transformData = (data: Record<string, any>) => {
  const payload = { ...data };
  if (!payload.password) {
    delete payload.password;
  }
  return payload;
};

export function UsersPage() {
  return (
    <CrudPage<AuthUser>
      title="Users"
      endpoint="users"
      fields={fields}
      viewPermission="users.view"
      createPermission="users.create"
      updatePermission="users.update"
      deletePermission="users.delete"
      transformData={transformData}
    />
  );
}

export default UsersPage;
