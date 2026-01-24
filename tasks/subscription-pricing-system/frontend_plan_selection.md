# Frontend Plan Selection and Comparison

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Frontend
**Complexity**: Medium
**Dependencies**: `backend_plan_management.md`, `backend_subscription_service.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement the plan selection and comparison UI including pricing page, plan comparison table, billing cycle toggle (monthly/yearly), and upgrade modal. Highlights SMART as recommended plan per PRD REQ-02, REQ-13.

**Architecture Impact**: Adds new pages for pricing and plan selection. Integrates with Stripe Elements for payment method collection. Uses existing design system components.

**Risk Assessment**:
- **Medium**: Stripe Elements integration for secure card input
- **Low**: Responsive design for plan comparison table
- **Low**: Accessibility for plan selection

## Component Architecture

### Types

**File**: `src/types/subscription.ts`

```typescript
export interface Plan {
  id: number;
  name: string;
  slug: string;
  description: string;
  pricing: {
    monthly: {
      amount: number;
      currency: string;
    };
    yearly: {
      amount: number;
      monthly_equivalent: number;
      discount_percentage: number;
      currency: string;
    };
  };
  features: Record<string, boolean | string>;
  limits: Record<string, number | 'unlimited'>;
  is_recommended: boolean;
}

export interface Subscription {
  id: number;
  plan: Plan;
  billing_cycle: 'monthly' | 'yearly';
  status: 'active' | 'trialing' | 'canceled' | 'past_due';
  trial_ends_at: string | null;
  ends_at: string | null;
  current_period_end: string;
}

export interface UsageMetrics {
  reservations: {
    current: number;
    limit: number | 'unlimited';
    percentage: number;
  };
  users: {
    current: number;
    limit: number | 'unlimited';
    percentage: number;
  };
  services: {
    current: number;
    limit: number | 'unlimited';
    percentage: number;
  };
}

export type BillingCycle = 'monthly' | 'yearly';
```

### Plan Selection Page

**File**: `src/pages/subscription/PlanSelectionPage.tsx`

```tsx
import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PlanCard } from '@/components/subscription/PlanCard';
import { BillingCycleToggle } from '@/components/subscription/BillingCycleToggle';
import { UpgradeModal } from '@/components/subscription/UpgradeModal';
import { api } from '@/lib/api';
import { Plan, BillingCycle } from '@/types/subscription';

export function PlanSelectionPage() {
  const [billingCycle, setBillingCycle] = useState<BillingCycle>('yearly');
  const [selectedPlan, setSelectedPlan] = useState<Plan | null>(null);
  const [showUpgradeModal, setShowUpgradeModal] = useState(false);

  const { data: plans, isLoading } = useQuery({
    queryKey: ['plans'],
    queryFn: () => api.get<{ plans: Plan[] }>('/plans').then(res => res.plans),
  });

  const { data: currentSubscription } = useQuery({
    queryKey: ['subscription'],
    queryFn: () => api.get('/subscriptions'),
  });

  const handleSelectPlan = (plan: Plan) => {
    setSelectedPlan(plan);
    setShowUpgradeModal(true);
  };

  if (isLoading) {
    return <div className="animate-pulse">Loading plans...</div>;
  }

  return (
    <div className="max-w-7xl mx-auto px-4 py-12">
      <div className="text-center mb-12">
        <h1 className="text-4xl font-bold text-gray-900 mb-4">
          Choose Your Plan
        </h1>
        <p className="text-xl text-gray-600 mb-8">
          Get more features for less. We're 40% cheaper than the competition.
        </p>

        <BillingCycleToggle
          value={billingCycle}
          onChange={setBillingCycle}
          yearlyDiscount={31}
        />
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6">
        {plans?.map((plan) => (
          <PlanCard
            key={plan.id}
            plan={plan}
            billingCycle={billingCycle}
            isCurrentPlan={currentSubscription?.plan?.id === plan.id}
            onSelect={() => handleSelectPlan(plan)}
          />
        ))}
      </div>

      <div className="mt-16">
        <h2 className="text-2xl font-bold text-center mb-8">
          Compare All Features
        </h2>
        <PlanComparisonTable plans={plans || []} billingCycle={billingCycle} />
      </div>

      {showUpgradeModal && selectedPlan && (
        <UpgradeModal
          plan={selectedPlan}
          billingCycle={billingCycle}
          onClose={() => setShowUpgradeModal(false)}
        />
      )}
    </div>
  );
}
```

### Plan Card Component

**File**: `src/components/subscription/PlanCard.tsx`

```tsx
import React from 'react';
import { Check, Lock } from 'lucide-react';
import { Plan, BillingCycle } from '@/types/subscription';
import { cn } from '@/lib/utils';

interface PlanCardProps {
  plan: Plan;
  billingCycle: BillingCycle;
  isCurrentPlan: boolean;
  onSelect: () => void;
}

export function PlanCard({
  plan,
  billingCycle,
  isCurrentPlan,
  onSelect,
}: PlanCardProps) {
  const price = billingCycle === 'yearly'
    ? plan.pricing.yearly.monthly_equivalent
    : plan.pricing.monthly.amount;

  const yearlyTotal = plan.pricing.yearly.amount;
  const discount = plan.pricing.yearly.discount_percentage;

  const isRecommended = plan.is_recommended;
  const isFree = plan.slug === 'free';

  return (
    <div
      className={cn(
        'relative flex flex-col rounded-2xl border p-6 shadow-sm',
        isRecommended
          ? 'border-blue-500 ring-2 ring-blue-500'
          : 'border-gray-200',
        isCurrentPlan && 'bg-gray-50'
      )}
    >
      {isRecommended && (
        <div className="absolute -top-4 left-1/2 -translate-x-1/2">
          <span className="bg-blue-500 text-white text-sm font-medium px-4 py-1 rounded-full">
            Best Value
          </span>
        </div>
      )}

      <div className="mb-4">
        <h3 className="text-xl font-bold text-gray-900">{plan.name}</h3>
        <p className="text-sm text-gray-500 mt-1">{plan.description}</p>
      </div>

      <div className="mb-6">
        <div className="flex items-baseline">
          <span className="text-4xl font-bold text-gray-900">
            {isFree ? 'Free' : `€${price.toFixed(2)}`}
          </span>
          {!isFree && (
            <span className="ml-1 text-gray-500">/month</span>
          )}
        </div>

        {billingCycle === 'yearly' && !isFree && (
          <div className="mt-2 space-y-1">
            <p className="text-sm text-gray-500">
              €{yearlyTotal} billed yearly
            </p>
            <p className="text-sm font-medium text-green-600">
              Save {discount}%
            </p>
          </div>
        )}
      </div>

      <ul className="mb-6 space-y-3 flex-grow">
        <PlanFeature
          text={`${formatLimit(plan.limits.reservations_per_month)} reservations/month`}
        />
        <PlanFeature
          text={`${formatLimit(plan.limits.users)} ${plan.limits.users === 1 ? 'user' : 'users'}`}
        />
        <PlanFeature
          text={`${formatLimit(plan.limits.locations)} ${plan.limits.locations === 1 ? 'location' : 'locations'}`}
        />
        {plan.features.google_calendar_sync && (
          <PlanFeature text="Google Calendar sync" />
        )}
        {plan.features.payment_gateway && (
          <PlanFeature text="Payment gateway" />
        )}
        {plan.features.sms_reminders && (
          <PlanFeature text={`${plan.limits.sms_credits_per_month} SMS/month`} />
        )}
      </ul>

      <button
        onClick={onSelect}
        disabled={isCurrentPlan}
        className={cn(
          'w-full py-3 px-4 rounded-lg font-medium transition-colors',
          isRecommended
            ? 'bg-blue-500 text-white hover:bg-blue-600'
            : 'bg-gray-100 text-gray-900 hover:bg-gray-200',
          isCurrentPlan && 'opacity-50 cursor-not-allowed'
        )}
      >
        {isCurrentPlan ? 'Current Plan' : isFree ? 'Get Started' : 'Choose Plan'}
      </button>
    </div>
  );
}

function PlanFeature({ text }: { text: string }) {
  return (
    <li className="flex items-center text-sm text-gray-600">
      <Check className="h-4 w-4 text-green-500 mr-2 flex-shrink-0" />
      {text}
    </li>
  );
}

function formatLimit(value: number | 'unlimited'): string {
  if (value === 'unlimited' || value === -1) {
    return 'Unlimited';
  }
  return value.toLocaleString();
}
```

### Billing Cycle Toggle

**File**: `src/components/subscription/BillingCycleToggle.tsx`

```tsx
import React from 'react';
import { BillingCycle } from '@/types/subscription';
import { cn } from '@/lib/utils';

interface BillingCycleToggleProps {
  value: BillingCycle;
  onChange: (value: BillingCycle) => void;
  yearlyDiscount: number;
}

export function BillingCycleToggle({
  value,
  onChange,
  yearlyDiscount,
}: BillingCycleToggleProps) {
  return (
    <div className="flex items-center justify-center gap-4">
      <button
        onClick={() => onChange('monthly')}
        className={cn(
          'px-4 py-2 rounded-lg font-medium transition-colors',
          value === 'monthly'
            ? 'bg-blue-500 text-white'
            : 'text-gray-600 hover:text-gray-900'
        )}
      >
        Monthly
      </button>

      <button
        onClick={() => onChange('yearly')}
        className={cn(
          'px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2',
          value === 'yearly'
            ? 'bg-blue-500 text-white'
            : 'text-gray-600 hover:text-gray-900'
        )}
      >
        Yearly
        <span
          className={cn(
            'text-xs px-2 py-0.5 rounded-full',
            value === 'yearly'
              ? 'bg-white/20 text-white'
              : 'bg-green-100 text-green-700'
          )}
        >
          Save {yearlyDiscount}%
        </span>
      </button>
    </div>
  );
}
```

### Upgrade Modal

**File**: `src/components/subscription/UpgradeModal.tsx`

```tsx
import React, { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { X, CreditCard, Check, AlertCircle } from 'lucide-react';
import { loadStripe } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { Plan, BillingCycle } from '@/types/subscription';
import { api } from '@/lib/api';

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLIC_KEY);

interface UpgradeModalProps {
  plan: Plan;
  billingCycle: BillingCycle;
  onClose: () => void;
}

export function UpgradeModal({ plan, billingCycle, onClose }: UpgradeModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-6 border-b">
          <h2 className="text-xl font-bold">Upgrade to {plan.name}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            <X className="h-6 w-6" />
          </button>
        </div>

        <Elements stripe={stripePromise}>
          <UpgradeForm plan={plan} billingCycle={billingCycle} onClose={onClose} />
        </Elements>
      </div>
    </div>
  );
}

function UpgradeForm({
  plan,
  billingCycle,
  onClose,
}: UpgradeModalProps) {
  const stripe = useStripe();
  const elements = useElements();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  const price = billingCycle === 'yearly'
    ? plan.pricing.yearly.amount
    : plan.pricing.monthly.amount;

  const priceLabel = billingCycle === 'yearly'
    ? `€${price}/year`
    : `€${price}/month`;

  const createSubscription = useMutation({
    mutationFn: async (paymentMethodId: string) => {
      return api.post('/subscriptions', {
        plan_id: plan.id,
        billing_cycle: billingCycle,
        payment_method_id: paymentMethodId,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['subscription'] });
      onClose();
    },
    onError: (err: any) => {
      setError(err.response?.data?.message || 'Failed to create subscription');
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
      setError(stripeError.message || 'Payment failed');
      return;
    }

    if (paymentMethod) {
      createSubscription.mutate(paymentMethod.id);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="p-6 space-y-6">
      <div className="bg-gray-50 rounded-lg p-4">
        <div className="flex justify-between items-center mb-2">
          <span className="font-medium">{plan.name} Plan</span>
          <span className="font-bold">{priceLabel}</span>
        </div>
        <p className="text-sm text-gray-500">
          {billingCycle === 'yearly'
            ? `Billed annually (€${plan.pricing.yearly.monthly_equivalent.toFixed(2)}/month)`
            : 'Billed monthly'}
        </p>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          <CreditCard className="h-4 w-4 inline mr-2" />
          Card Details
        </label>
        <div className="border rounded-lg p-3">
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
      </div>

      {error && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertCircle className="h-4 w-4" />
          {error}
        </div>
      )}

      <div className="space-y-3">
        <button
          type="submit"
          disabled={!stripe || createSubscription.isPending}
          className="w-full bg-blue-500 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {createSubscription.isPending ? 'Processing...' : `Subscribe for ${priceLabel}`}
        </button>

        <p className="text-xs text-center text-gray-500">
          You can cancel anytime. By subscribing, you agree to our Terms of Service.
        </p>
      </div>

      <div className="border-t pt-4">
        <p className="text-sm font-medium text-gray-700 mb-2">
          What you'll get:
        </p>
        <ul className="space-y-2">
          <FeatureItem text={`${formatLimit(plan.limits.reservations_per_month)} reservations/month`} />
          <FeatureItem text={`${formatLimit(plan.limits.users)} team members`} />
          {plan.features.google_calendar_sync && (
            <FeatureItem text="Google Calendar integration" />
          )}
          {plan.features.payment_gateway && (
            <FeatureItem text="Accept online payments" />
          )}
        </ul>
      </div>
    </form>
  );
}

function FeatureItem({ text }: { text: string }) {
  return (
    <li className="flex items-center text-sm text-gray-600">
      <Check className="h-4 w-4 text-green-500 mr-2" />
      {text}
    </li>
  );
}

function formatLimit(value: number | 'unlimited'): string {
  if (value === 'unlimited' || value === -1) {
    return 'Unlimited';
  }
  return value.toLocaleString();
}
```

### Plan Comparison Table

**File**: `src/components/subscription/PlanComparisonTable.tsx`

```tsx
import React from 'react';
import { Check, X, Minus } from 'lucide-react';
import { Plan, BillingCycle } from '@/types/subscription';

interface PlanComparisonTableProps {
  plans: Plan[];
  billingCycle: BillingCycle;
}

const FEATURE_LABELS: Record<string, string> = {
  online_booking_widget: 'Online Booking Widget',
  manual_reservations: 'Manual Reservations',
  calendar_view: 'Calendar View',
  client_database: 'Client Database',
  email_confirmations: 'Email Confirmations',
  email_reminders: 'Email Reminders',
  sms_reminders: 'SMS Reminders',
  custom_logo: 'Custom Logo',
  custom_colors: 'Custom Colors',
  custom_booking_url: 'Custom Booking URL',
  custom_domain: 'Custom Domain',
  white_label: 'White Label',
  google_calendar_sync: 'Google Calendar Sync',
  payment_gateway: 'Payment Gateway',
  api_access: 'API Access',
  zapier_integration: 'Zapier Integration',
  multi_language: 'Multi-Language',
  staff_permissions: 'Staff Permissions',
  client_segmentation: 'Client Segmentation',
  waitlist_management: 'Waitlist',
  recurring_appointments: 'Recurring Appointments',
  gift_vouchers: 'Gift Vouchers',
  reports_statistics: 'Reports & Statistics',
};

export function PlanComparisonTable({ plans, billingCycle }: PlanComparisonTableProps) {
  const features = Object.keys(FEATURE_LABELS);

  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse">
        <thead>
          <tr>
            <th className="text-left p-4 bg-gray-50 sticky left-0">Feature</th>
            {plans.map((plan) => (
              <th key={plan.id} className="text-center p-4 bg-gray-50 min-w-[120px]">
                <div className="font-bold">{plan.name}</div>
                <div className="text-sm font-normal text-gray-500">
                  {plan.slug === 'free'
                    ? 'Free'
                    : `€${(billingCycle === 'yearly'
                        ? plan.pricing.yearly.monthly_equivalent
                        : plan.pricing.monthly.amount
                      ).toFixed(2)}/mo`}
                </div>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {features.map((feature) => (
            <tr key={feature} className="border-b">
              <td className="p-4 text-sm text-gray-700 sticky left-0 bg-white">
                {FEATURE_LABELS[feature]}
              </td>
              {plans.map((plan) => (
                <td key={plan.id} className="text-center p-4">
                  <FeatureValue value={plan.features[feature]} />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function FeatureValue({ value }: { value: boolean | string | undefined }) {
  if (value === true) {
    return <Check className="h-5 w-5 text-green-500 mx-auto" />;
  }

  if (value === false || value === undefined) {
    return <X className="h-5 w-5 text-gray-300 mx-auto" />;
  }

  if (value === 'basic' || value === 'advanced' || value === 'full') {
    return (
      <span className="text-sm capitalize text-gray-700">{value}</span>
    );
  }

  return <Minus className="h-5 w-5 text-gray-300 mx-auto" />;
}
```

## API Integration

### API Hooks

**File**: `src/hooks/useSubscription.ts`

```typescript
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { Plan, Subscription, UsageMetrics } from '@/types/subscription';

export function usePlans() {
  return useQuery({
    queryKey: ['plans'],
    queryFn: () => api.get<{ plans: Plan[] }>('/plans').then(res => res.plans),
  });
}

export function useSubscription() {
  return useQuery({
    queryKey: ['subscription'],
    queryFn: () => api.get<Subscription>('/subscriptions'),
  });
}

export function useUsageMetrics() {
  return useQuery({
    queryKey: ['usage'],
    queryFn: () => api.get<UsageMetrics>('/subscription/usage'),
  });
}

export function useCreateSubscription() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: {
      plan_id: number;
      billing_cycle: 'monthly' | 'yearly';
      payment_method_id?: string;
    }) => api.post('/subscriptions', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['subscription'] });
      queryClient.invalidateQueries({ queryKey: ['usage'] });
    },
  });
}

export function useUpgradeSubscription() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: {
      plan_id: number;
      billing_cycle?: 'monthly' | 'yearly';
    }) => api.post('/subscriptions/upgrade', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['subscription'] });
      queryClient.invalidateQueries({ queryKey: ['usage'] });
    },
  });
}
```

## Testing Strategy

### E2E Test
- `TestPlanSelection` covering plan display, billing toggle, upgrade flow
- Verify: Plans render correctly, prices update on toggle, Stripe integration works

### Manual Verification
- View pricing page
- Toggle between monthly/yearly
- Complete upgrade flow with test card

## Implementation Steps

1. **Small** - Create subscription TypeScript types
2. **Medium** - Create PlanCard component
3. **Small** - Create BillingCycleToggle component
4. **Large** - Create UpgradeModal with Stripe Elements
5. **Medium** - Create PlanComparisonTable component
6. **Medium** - Create PlanSelectionPage
7. **Medium** - Create API hooks for subscription
8. **Small** - Add Stripe.js dependency
9. **Small** - Add routes for subscription pages
10. **Medium** - Style and responsive design
11. **Medium** - Write component tests
12. **Small** - Accessibility testing

## Cross-Task Dependencies

- **Depends on**: `backend_plan_management.md`, `backend_subscription_service.md`
- **Blocks**: None
- **Parallel work**: Can work alongside `frontend_subscription_dashboard.md`
