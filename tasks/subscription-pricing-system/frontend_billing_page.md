# Frontend Billing Page

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Frontend
**Complexity**: Medium
**Dependencies**: `backend_billing_invoicing.md`, `backend_subscription_service.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement the billing management page including invoice history, payment method management, and subscription cancellation. Uses Stripe Elements for secure card updates per PRD REQ-03, REQ-09.

**Architecture Impact**: Adds billing page with invoice list, payment method forms, and subscription management. Integrates with Stripe for payment method updates.

**Risk Assessment**:
- **Medium**: Stripe Elements integration for payment updates
- **Low**: Invoice download functionality
- **Low**: Cancellation flow with confirmation

## Component Architecture

### Billing Page

**File**: `src/pages/billing/BillingPage.tsx`

```tsx
import React, { useState } from 'react';
import { CreditCard, FileText, Settings } from 'lucide-react';
import { PaymentMethodsSection } from '@/components/billing/PaymentMethodsSection';
import { InvoiceHistorySection } from '@/components/billing/InvoiceHistorySection';
import { SubscriptionManagementSection } from '@/components/billing/SubscriptionManagementSection';
import { cn } from '@/lib/utils';

type Tab = 'payment-methods' | 'invoices' | 'subscription';

export function BillingPage() {
  const [activeTab, setActiveTab] = useState<Tab>('payment-methods');

  const tabs = [
    { id: 'payment-methods' as Tab, label: 'Payment Methods', icon: CreditCard },
    { id: 'invoices' as Tab, label: 'Invoices', icon: FileText },
    { id: 'subscription' as Tab, label: 'Subscription', icon: Settings },
  ];

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Billing</h1>

      {/* Tab Navigation */}
      <div className="border-b border-gray-200 mb-6">
        <nav className="flex space-x-8">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={cn(
                'flex items-center gap-2 py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              )}
            >
              <tab.icon className="h-5 w-5" />
              {tab.label}
            </button>
          ))}
        </nav>
      </div>

      {/* Tab Content */}
      {activeTab === 'payment-methods' && <PaymentMethodsSection />}
      {activeTab === 'invoices' && <InvoiceHistorySection />}
      {activeTab === 'subscription' && <SubscriptionManagementSection />}
    </div>
  );
}
```

### Payment Methods Section

**File**: `src/components/billing/PaymentMethodsSection.tsx`

```tsx
import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CreditCard, Plus, Trash2, Star, AlertCircle } from 'lucide-react';
import { loadStripe } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { api } from '@/lib/api';
import { cn } from '@/lib/utils';

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLIC_KEY);

interface PaymentMethod {
  id: number;
  type: string;
  card_brand: string;
  card_last4: string;
  card_exp_month: number;
  card_exp_year: number;
  is_default: boolean;
}

export function PaymentMethodsSection() {
  const [showAddForm, setShowAddForm] = useState(false);

  const { data: paymentMethods, isLoading } = useQuery({
    queryKey: ['payment-methods'],
    queryFn: () =>
      api.get<{ payment_methods: PaymentMethod[] }>('/billing/payment-methods')
        .then(res => res.payment_methods),
  });

  if (isLoading) {
    return <div className="animate-pulse">Loading payment methods...</div>;
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-gray-900">Payment Methods</h2>
        {!showAddForm && (
          <button
            onClick={() => setShowAddForm(true)}
            className="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium"
          >
            <Plus className="h-4 w-4" />
            Add Payment Method
          </button>
        )}
      </div>

      {/* Add Payment Method Form */}
      {showAddForm && (
        <Elements stripe={stripePromise}>
          <AddPaymentMethodForm onClose={() => setShowAddForm(false)} />
        </Elements>
      )}

      {/* Payment Methods List */}
      {paymentMethods && paymentMethods.length > 0 ? (
        <div className="space-y-3">
          {paymentMethods.map((method) => (
            <PaymentMethodCard key={method.id} paymentMethod={method} />
          ))}
        </div>
      ) : (
        <div className="text-center py-8 bg-gray-50 rounded-lg">
          <CreditCard className="h-12 w-12 text-gray-300 mx-auto mb-3" />
          <p className="text-gray-500">No payment methods added yet</p>
        </div>
      )}
    </div>
  );
}

function PaymentMethodCard({ paymentMethod }: { paymentMethod: PaymentMethod }) {
  const queryClient = useQueryClient();

  const setDefault = useMutation({
    mutationFn: () => api.post(`/billing/payment-methods/${paymentMethod.id}/default`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['payment-methods'] }),
  });

  const remove = useMutation({
    mutationFn: () => api.delete(`/billing/payment-methods/${paymentMethod.id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['payment-methods'] }),
  });

  const brandIcons: Record<string, string> = {
    visa: '/icons/visa.svg',
    mastercard: '/icons/mastercard.svg',
    amex: '/icons/amex.svg',
  };

  const isExpiringSoon = (() => {
    const expDate = new Date(paymentMethod.card_exp_year, paymentMethod.card_exp_month - 1);
    const now = new Date();
    const diffDays = (expDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);
    return diffDays <= 30 && diffDays > 0;
  })();

  const isExpired = (() => {
    const expDate = new Date(paymentMethod.card_exp_year, paymentMethod.card_exp_month - 1);
    return expDate < new Date();
  })();

  return (
    <div
      className={cn(
        'flex items-center justify-between p-4 border rounded-lg',
        paymentMethod.is_default ? 'border-blue-200 bg-blue-50' : 'border-gray-200'
      )}
    >
      <div className="flex items-center gap-4">
        <div className="w-12 h-8 flex items-center justify-center bg-white rounded border">
          {brandIcons[paymentMethod.card_brand] ? (
            <img
              src={brandIcons[paymentMethod.card_brand]}
              alt={paymentMethod.card_brand}
              className="h-6"
            />
          ) : (
            <CreditCard className="h-5 w-5 text-gray-400" />
          )}
        </div>

        <div>
          <div className="flex items-center gap-2">
            <span className="font-medium capitalize">
              {paymentMethod.card_brand}
            </span>
            <span className="text-gray-500">
              ending in {paymentMethod.card_last4}
            </span>
            {paymentMethod.is_default && (
              <span className="inline-flex items-center gap-1 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                <Star className="h-3 w-3" />
                Default
              </span>
            )}
          </div>
          <div className="flex items-center gap-2 text-sm text-gray-500">
            <span>
              Expires {paymentMethod.card_exp_month}/{paymentMethod.card_exp_year}
            </span>
            {isExpiringSoon && (
              <span className="flex items-center gap-1 text-yellow-600">
                <AlertCircle className="h-3 w-3" />
                Expiring soon
              </span>
            )}
            {isExpired && (
              <span className="flex items-center gap-1 text-red-600">
                <AlertCircle className="h-3 w-3" />
                Expired
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="flex items-center gap-2">
        {!paymentMethod.is_default && (
          <button
            onClick={() => setDefault.mutate()}
            disabled={setDefault.isPending}
            className="text-sm text-blue-600 hover:text-blue-700"
          >
            Set as default
          </button>
        )}
        <button
          onClick={() => {
            if (confirm('Remove this payment method?')) {
              remove.mutate();
            }
          }}
          disabled={remove.isPending || paymentMethod.is_default}
          className={cn(
            'p-2 rounded-lg hover:bg-gray-100',
            paymentMethod.is_default && 'opacity-50 cursor-not-allowed'
          )}
        >
          <Trash2 className="h-4 w-4 text-gray-400" />
        </button>
      </div>
    </div>
  );
}

function AddPaymentMethodForm({ onClose }: { onClose: () => void }) {
  const stripe = useStripe();
  const elements = useElements();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  const addPaymentMethod = useMutation({
    mutationFn: (paymentMethodId: string) =>
      api.post('/billing/payment-methods', {
        payment_method_id: paymentMethodId,
        set_as_default: true,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payment-methods'] });
      onClose();
    },
    onError: (err: any) => {
      setError(err.response?.data?.message || 'Failed to add payment method');
    },
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!stripe || !elements) {
      return;
    }

    setError(null);

    const cardElement = elements.getElement(CardElement);
    if (!cardElement) {
      return;
    }

    const { error: stripeError, paymentMethod } = await stripe.createPaymentMethod({
      type: 'card',
      card: cardElement,
    });

    if (stripeError) {
      setError(stripeError.message || 'Payment method creation failed');
      return;
    }

    if (paymentMethod) {
      addPaymentMethod.mutate(paymentMethod.id);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="bg-gray-50 rounded-lg p-4">
      <h3 className="font-medium text-gray-900 mb-4">Add New Payment Method</h3>

      <div className="bg-white border rounded-lg p-3 mb-4">
        <CardElement
          options={{
            style: {
              base: {
                fontSize: '16px',
                color: '#374151',
                '::placeholder': {
                  color: '#9CA3AF',
                },
              },
            },
          }}
        />
      </div>

      {error && (
        <div className="flex items-center gap-2 text-red-600 text-sm mb-4">
          <AlertCircle className="h-4 w-4" />
          {error}
        </div>
      )}

      <div className="flex items-center gap-3">
        <button
          type="submit"
          disabled={!stripe || addPaymentMethod.isPending}
          className="bg-blue-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50"
        >
          {addPaymentMethod.isPending ? 'Adding...' : 'Add Card'}
        </button>
        <button
          type="button"
          onClick={onClose}
          className="text-gray-600 hover:text-gray-800"
        >
          Cancel
        </button>
      </div>
    </form>
  );
}
```

### Invoice History Section

**File**: `src/components/billing/InvoiceHistorySection.tsx`

```tsx
import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { FileText, Download, Check, Clock, AlertCircle } from 'lucide-react';
import { api } from '@/lib/api';
import { cn } from '@/lib/utils';

interface Invoice {
  id: number;
  invoice_number: string;
  amount_gross: number;
  currency: string;
  status: 'draft' | 'open' | 'paid' | 'void' | 'uncollectible';
  created_at: string;
  billing_period_start: string;
  billing_period_end: string;
}

export function InvoiceHistorySection() {
  const { data: invoices, isLoading } = useQuery({
    queryKey: ['invoices'],
    queryFn: () =>
      api.get<{ invoices: Invoice[] }>('/billing/invoices')
        .then(res => res.invoices),
  });

  if (isLoading) {
    return <div className="animate-pulse">Loading invoices...</div>;
  }

  return (
    <div className="space-y-6">
      <h2 className="text-lg font-semibold text-gray-900">Invoice History</h2>

      {invoices && invoices.length > 0 ? (
        <div className="overflow-hidden border rounded-lg">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Invoice
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Date
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Period
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Amount
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {invoices.map((invoice) => (
                <InvoiceRow key={invoice.id} invoice={invoice} />
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="text-center py-12 bg-gray-50 rounded-lg">
          <FileText className="h-12 w-12 text-gray-300 mx-auto mb-3" />
          <p className="text-gray-500">No invoices yet</p>
        </div>
      )}
    </div>
  );
}

function InvoiceRow({ invoice }: { invoice: Invoice }) {
  const handleDownload = () => {
    window.open(`/api/billing/invoices/${invoice.id}/download`, '_blank');
  };

  const statusConfig = {
    paid: { icon: Check, color: 'text-green-600 bg-green-50', label: 'Paid' },
    open: { icon: Clock, color: 'text-yellow-600 bg-yellow-50', label: 'Open' },
    void: { icon: AlertCircle, color: 'text-gray-600 bg-gray-50', label: 'Void' },
    draft: { icon: FileText, color: 'text-gray-600 bg-gray-50', label: 'Draft' },
    uncollectible: { icon: AlertCircle, color: 'text-red-600 bg-red-50', label: 'Uncollectible' },
  };

  const status = statusConfig[invoice.status];
  const StatusIcon = status.icon;

  return (
    <tr className="hover:bg-gray-50">
      <td className="px-6 py-4 whitespace-nowrap">
        <span className="font-medium text-gray-900">{invoice.invoice_number}</span>
      </td>
      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
        {new Date(invoice.created_at).toLocaleDateString()}
      </td>
      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
        {new Date(invoice.billing_period_start).toLocaleDateString()} -{' '}
        {new Date(invoice.billing_period_end).toLocaleDateString()}
      </td>
      <td className="px-6 py-4 whitespace-nowrap">
        <span className="font-medium text-gray-900">
          {invoice.currency} {invoice.amount_gross.toFixed(2)}
        </span>
      </td>
      <td className="px-6 py-4 whitespace-nowrap">
        <span
          className={cn(
            'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium',
            status.color
          )}
        >
          <StatusIcon className="h-3 w-3" />
          {status.label}
        </span>
      </td>
      <td className="px-6 py-4 whitespace-nowrap text-right">
        <button
          onClick={handleDownload}
          className="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 text-sm font-medium"
        >
          <Download className="h-4 w-4" />
          PDF
        </button>
      </td>
    </tr>
  );
}
```

### Subscription Management Section

**File**: `src/components/billing/SubscriptionManagementSection.tsx`

```tsx
import React, { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, ArrowUpRight } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useSubscription } from '@/hooks/useSubscription';
import { api } from '@/lib/api';
import { CancellationModal } from './CancellationModal';

export function SubscriptionManagementSection() {
  const { data: subscription } = useSubscription();
  const [showCancelModal, setShowCancelModal] = useState(false);
  const queryClient = useQueryClient();

  const resumeSubscription = useMutation({
    mutationFn: () => api.post('/subscriptions/resume'),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['subscription'] }),
  });

  if (!subscription) {
    return <div>Loading...</div>;
  }

  const isCanceled = subscription.ends_at !== null;

  return (
    <div className="space-y-6">
      <h2 className="text-lg font-semibold text-gray-900">Subscription Management</h2>

      {/* Current Plan */}
      <div className="bg-gray-50 rounded-lg p-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className="font-semibold text-gray-900">{subscription.plan.name} Plan</h3>
            <p className="text-sm text-gray-500">
              {subscription.billing_cycle === 'yearly' ? 'Annual billing' : 'Monthly billing'}
            </p>
          </div>
          <Link
            to="/subscription/plans"
            className="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 font-medium"
          >
            Change Plan
            <ArrowUpRight className="h-4 w-4" />
          </Link>
        </div>

        <div className="text-sm text-gray-600 space-y-1">
          <p>
            <span className="font-medium">Current period ends:</span>{' '}
            {new Date(subscription.current_period_end).toLocaleDateString()}
          </p>
          {subscription.status === 'trialing' && subscription.trial_ends_at && (
            <p>
              <span className="font-medium">Trial ends:</span>{' '}
              {new Date(subscription.trial_ends_at).toLocaleDateString()}
            </p>
          )}
        </div>
      </div>

      {/* Cancellation Status */}
      {isCanceled && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <AlertTriangle className="h-5 w-5 text-yellow-600 mt-0.5" />
            <div className="flex-1">
              <h4 className="font-medium text-yellow-900">Subscription Ending</h4>
              <p className="text-sm text-yellow-700 mt-1">
                Your subscription will end on{' '}
                {new Date(subscription.ends_at!).toLocaleDateString()}.
                After this date, you'll be moved to the FREE plan.
              </p>
              <button
                onClick={() => resumeSubscription.mutate()}
                disabled={resumeSubscription.isPending}
                className="mt-3 bg-yellow-600 text-white text-sm font-medium py-2 px-4 rounded-lg hover:bg-yellow-700 disabled:opacity-50"
              >
                {resumeSubscription.isPending ? 'Resuming...' : 'Resume Subscription'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Cancel Subscription */}
      {!isCanceled && subscription.plan.slug !== 'free' && (
        <div className="border-t pt-6">
          <h3 className="font-medium text-gray-900 mb-2">Cancel Subscription</h3>
          <p className="text-sm text-gray-500 mb-4">
            If you cancel, you'll still have access until the end of your current billing period.
          </p>
          <button
            onClick={() => setShowCancelModal(true)}
            className="text-red-600 hover:text-red-700 text-sm font-medium"
          >
            Cancel Subscription
          </button>
        </div>
      )}

      {showCancelModal && (
        <CancellationModal onClose={() => setShowCancelModal(false)} />
      )}
    </div>
  );
}
```

### Cancellation Modal

**File**: `src/components/billing/CancellationModal.tsx`

```tsx
import React, { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { X, AlertTriangle } from 'lucide-react';
import { api } from '@/lib/api';
import { useSubscription } from '@/hooks/useSubscription';

interface CancellationModalProps {
  onClose: () => void;
}

const CANCELLATION_REASONS = [
  'Too expensive',
  'Not using it enough',
  'Missing features I need',
  'Switching to another service',
  'Business closed',
  'Other',
];

export function CancellationModal({ onClose }: CancellationModalProps) {
  const { data: subscription } = useSubscription();
  const queryClient = useQueryClient();
  const [reason, setReason] = useState('');
  const [feedback, setFeedback] = useState('');

  const cancelSubscription = useMutation({
    mutationFn: () =>
      api.post('/subscriptions/cancel', {
        reason: reason || undefined,
        feedback: feedback || undefined,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['subscription'] });
      onClose();
    },
  });

  const accessEndsAt = subscription?.current_period_end
    ? new Date(subscription.current_period_end).toLocaleDateString()
    : 'end of billing period';

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-6 border-b">
          <h2 className="text-xl font-bold text-gray-900">Cancel Subscription</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X className="h-6 w-6" />
          </button>
        </div>

        <div className="p-6 space-y-6">
          {/* Warning */}
          <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div className="flex items-start gap-3">
              <AlertTriangle className="h-5 w-5 text-yellow-600 mt-0.5" />
              <div>
                <p className="text-sm text-yellow-800">
                  If you cancel, you'll lose access to {subscription?.plan.name} features on{' '}
                  <strong>{accessEndsAt}</strong>.
                </p>
              </div>
            </div>
          </div>

          {/* What you'll lose */}
          <div>
            <h4 className="font-medium text-gray-900 mb-2">What you'll lose:</h4>
            <ul className="text-sm text-gray-600 space-y-1">
              <li>- Access to advanced features</li>
              <li>- Higher usage limits</li>
              <li>- Priority support</li>
              <li>- Custom branding options</li>
            </ul>
          </div>

          {/* Reason */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Why are you canceling? (optional)
            </label>
            <select
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              className="w-full border rounded-lg p-2.5"
            >
              <option value="">Select a reason</option>
              {CANCELLATION_REASONS.map((r) => (
                <option key={r} value={r}>
                  {r}
                </option>
              ))}
            </select>
          </div>

          {/* Feedback */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Any feedback for us? (optional)
            </label>
            <textarea
              value={feedback}
              onChange={(e) => setFeedback(e.target.value)}
              placeholder="Tell us how we could improve..."
              className="w-full border rounded-lg p-2.5 h-24 resize-none"
            />
          </div>

          {/* Actions */}
          <div className="flex items-center gap-3 pt-4">
            <button
              onClick={onClose}
              className="flex-1 py-3 px-4 border rounded-lg font-medium text-gray-700 hover:bg-gray-50"
            >
              Keep My Plan
            </button>
            <button
              onClick={() => cancelSubscription.mutate()}
              disabled={cancelSubscription.isPending}
              className="flex-1 py-3 px-4 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 disabled:opacity-50"
            >
              {cancelSubscription.isPending ? 'Canceling...' : 'Cancel Subscription'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
```

## Testing Strategy

### E2E Test
- `TestBillingPage` covering payment method CRUD, invoice download, cancellation
- Verify: Cards added/removed, invoices download, cancellation works

### Manual Verification
- Add and remove payment methods
- Download invoice PDF
- Complete cancellation flow

## Implementation Steps

1. **Medium** - Create BillingPage with tab navigation
2. **Large** - Create PaymentMethodsSection with Stripe Elements
3. **Medium** - Create InvoiceHistorySection with download
4. **Medium** - Create SubscriptionManagementSection
5. **Medium** - Create CancellationModal with feedback
6. **Small** - Add billing routes
7. **Medium** - Style and responsive design
8. **Medium** - Write component tests

## Cross-Task Dependencies

- **Depends on**: `backend_billing_invoicing.md`, `backend_subscription_service.md`
- **Blocks**: None
- **Parallel work**: Can work alongside `frontend_plan_selection.md`, `frontend_subscription_dashboard.md`
