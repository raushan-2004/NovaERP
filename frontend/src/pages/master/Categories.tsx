import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Category } from '../../types/api';

const fields: CrudField[] = [
  { name: 'name', label: 'Category Name', type: 'text', required: true, placeholder: 'e.g. Integrated Circuits' },
  { name: 'code', label: 'Category Code', type: 'text', required: true, placeholder: 'e.g. IC-CAT' },
  { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter category details...' },
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

export function CategoriesPage() {
  return (
    <CrudPage<Category>
      title="Categories"
      endpoint="categories"
      fields={fields}
      viewPermission="products.view"
      createPermission="products.create"
      updatePermission="products.update"
      deletePermission="products.delete"
    />
  );
}

export default CategoriesPage;
