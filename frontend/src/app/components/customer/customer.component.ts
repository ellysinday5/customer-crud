import { Component, OnInit, inject, ChangeDetectionStrategy, ChangeDetectorRef, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap } from 'rxjs/operators';
import { CustomerService } from '../../services/customer.service';
import { Customer, CustomerFormData } from '../../models/customer.model';

@Component({
  selector: 'app-customer',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './customer.component.html',
  styleUrls: ['./customer.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class CustomerComponent implements OnInit {
  private readonly customerService = inject(CustomerService);
  private readonly cdr = inject(ChangeDetectorRef);

  customers: Customer[] = [];
  filteredCustomers: Customer[] = [];
  searchQuery: string = '';

  isLoading: boolean = false;
  isSaving: boolean = false;
  isDeleting: boolean = false;

  toastMessage: string | null = null;
  toastType: 'success' | 'danger' = 'success';
  private toastTimeout: any;

  showFormModal: boolean = false;
  formMode: 'create' | 'edit' = 'create';
  editingCustomerId: number | null = null;
  formData: CustomerFormData = { first_name: '', last_name: '', email: '', contact_number: '' };
  formErrors: { [key: string]: string } = {};

  showViewModal: boolean = false;
  selectedCustomer: Customer | null = null;

  showDeleteModal: boolean = false;
  customerToDelete: Customer | null = null;

  showConfirmSaveModal: boolean = false;

  openDropdownId: number | null = null;

  private searchSubject = new Subject<string>();

  @HostListener('document:click')
  onDocumentClick(): void {
    if (this.openDropdownId !== null) {
      this.openDropdownId = null;
      this.cdr.markForCheck();
    }
  }

  ngOnInit(): void {
    this.loadCustomers();

    this.searchSubject.pipe(
      debounceTime(350),
      distinctUntilChanged(),
      switchMap((query) => {
        this.isLoading = true;
        this.cdr.markForCheck();
        return this.customerService.getCustomers(query);
      })
    ).subscribe({
      next: (data) => {
        this.customers = data;
        this.filteredCustomers = data;
        this.isLoading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.showToast('Search failed. Please verify the backend is running.', 'danger');
        this.isLoading = false;
        this.cdr.markForCheck();
      }
    });
  }

  trackById(_index: number, customer: Customer): number {
    return customer.id ?? _index;
  }

  toggleDropdown(id: number): void {
    this.openDropdownId = this.openDropdownId === id ? null : id;
    this.cdr.markForCheck();
  }

  closeDropdown(): void {
    this.openDropdownId = null;
    this.cdr.markForCheck();
  }

  loadCustomers(): void {
    this.isLoading = true;
    this.searchQuery = '';
    this.cdr.markForCheck();

    this.customerService.getCustomers().subscribe({
      next: (data) => {
        this.customers = data;
        this.filteredCustomers = data;
        this.isLoading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.showToast('Failed to load customers. Please verify the backend is running.', 'danger');
        this.isLoading = false;
        this.cdr.markForCheck();
      }
    });
  }

  onSearchChange(): void {
    this.searchSubject.next(this.searchQuery);
  }

  openCreateModal(): void {
    this.formMode = 'create';
    this.editingCustomerId = null;
    this.formData = { first_name: '', last_name: '', email: '', contact_number: '' };
    this.formErrors = {};
    this.showFormModal = true;
    this.cdr.markForCheck();
  }

  openEditModal(customer: Customer): void {
    this.showViewModal = false;
    this.formMode = 'edit';
    this.editingCustomerId = customer.id ?? null;
    this.formData = {
      first_name: customer.first_name,
      last_name: customer.last_name,
      email: customer.email,
      contact_number: customer.contact_number
    };
    this.formErrors = {};
    this.showFormModal = true;
    this.cdr.markForCheck();
  }

  openViewModal(customer: Customer): void {
    this.selectedCustomer = customer;
    this.showViewModal = true;
    this.cdr.markForCheck();
  }

  openDeleteModal(customer: Customer): void {
    this.customerToDelete = customer;
    this.showDeleteModal = true;
    this.cdr.markForCheck();
  }

  closeModals(): void {
    this.showFormModal = false;
    this.showViewModal = false;
    this.showDeleteModal = false;
    this.showConfirmSaveModal = false;
    this.formErrors = {};
    this.cdr.markForCheck();
  }

  requestSave(): void {
    if (!this.validateForm()) return;

    if (this.formMode === 'create') {
      this.executeSave();
    } else {
      this.showFormModal = false;
      this.showConfirmSaveModal = true;
      this.cdr.markForCheck();
    }
  }

  backToForm(): void {
    this.showConfirmSaveModal = false;
    this.showFormModal = true;
    this.cdr.markForCheck();
  }

  executeSave(): void {
    this.isSaving = true;
    this.showConfirmSaveModal = false;
    this.showFormModal = false;
    this.formErrors = {};
    this.cdr.markForCheck();

    const op$ = this.formMode === 'create'
      ? this.customerService.createCustomer(this.formData)
      : this.customerService.updateCustomer(this.editingCustomerId!, this.formData);

    op$.subscribe({
      next: (result) => {
        this.isSaving = false;
        const label = this.formMode === 'create' ? 'created' : 'updated';
        this.showToast(`Customer ${result.first_name} ${result.last_name} ${label} successfully!`, 'success');

        if (this.formMode === 'create') {
          this.customers = [result, ...this.customers];
        } else {
          this.customers = this.customers.map(c => c.id === result.id ? result : c);
        }
        this.filteredCustomers = [...this.customers];
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.isSaving = false;
        this.showFormModal = true;
        this.handleBackendErrors(err);
        this.cdr.markForCheck();
      }
    });
  }

  confirmDelete(): void {
    if (!this.customerToDelete?.id) return;

    this.isDeleting = true;
    const id = this.customerToDelete.id;
    const name = `${this.customerToDelete.first_name} ${this.customerToDelete.last_name}`;
    this.cdr.markForCheck();

    this.customerService.deleteCustomer(id).subscribe({
      next: () => {
        this.isDeleting = false;
        this.showDeleteModal = false;
        this.customerToDelete = null;
        this.customers = this.customers.filter(c => c.id !== id);
        this.filteredCustomers = this.filteredCustomers.filter(c => c.id !== id);
        this.showToast(`Customer ${name} deleted successfully!`, 'success');
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.isDeleting = false;
        this.showToast(err?.error?.message || 'Failed to delete customer.', 'danger');
        this.cdr.markForCheck();
      }
    });
  }

  private validateForm(): boolean {
    this.formErrors = {};
    let isValid = true;

    if (!this.formData.first_name.trim()) {
      this.formErrors['first_name'] = 'First name is required.';
      isValid = false;
    }
    if (!this.formData.last_name.trim()) {
      this.formErrors['last_name'] = 'Last name is required.';
      isValid = false;
    }
    if (!this.formData.email.trim()) {
      this.formErrors['email'] = 'Email address is required.';
      isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.formData.email.trim())) {
      this.formErrors['email'] = 'Please enter a valid email address.';
      isValid = false;
    }
    if (!this.formData.contact_number.trim()) {
      this.formErrors['contact_number'] = 'Contact number is required.';
      isValid = false;
    }

    this.cdr.markForCheck();
    return isValid;
  }

  private handleBackendErrors(err: any): void {
    if (err.status === 422 && err.error?.errors) {
      for (const key of Object.keys(err.error.errors)) {
        this.formErrors[key] = err.error.errors[key][0];
      }
    } else {
      this.showToast(err.error?.message || 'An error occurred while saving.', 'danger');
    }
    this.cdr.markForCheck();
  }

  showToast(message: string, type: 'success' | 'danger'): void {
    this.toastMessage = message;
    this.toastType = type;
    if (this.toastTimeout) clearTimeout(this.toastTimeout);
    this.toastTimeout = setTimeout(() => {
      this.toastMessage = null;
      this.cdr.markForCheck();
    }, 4000);
    this.cdr.markForCheck();
  }

  getInitials(firstName: string, lastName: string): string {
    const f = firstName?.charAt(0).toUpperCase() ?? '';
    const l = lastName?.charAt(0).toUpperCase() ?? '';
    return `${f}${l}` || 'C';
  }
}
