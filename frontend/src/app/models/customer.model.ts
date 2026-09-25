export interface Customer {
  id?: number;
  first_name: string;
  last_name: string;
  email: string;
  contact_number: string;
  created_at?: string;
  updated_at?: string;
}

export interface CustomerFormData {
  first_name: string;
  last_name: string;
  email: string;
  contact_number: string;
}
