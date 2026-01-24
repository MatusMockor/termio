# Frontend Subscription Dashboard

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Frontend
**Complexity**: Medium
**Dependencies**: `backend_subscription_service.md`, `backend_usage_limit_enforcement.md`, `backend_feature_gating.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement subscription status dashboard showing current plan, usage metrics, trial countdown, and upgrade prompts. Includes plan badge in header, usage progress bars, and contextual upgrade CTAs per PRD REQ-09.

**Architecture Impact**: Adds subscription widgets to existing dashboard. Creates reusable components for usage display and feature gates. Integrates with main layout for plan badge.

**Risk Assessment**:
- **Low**: Real-time usage updates - use react-query caching
- **Low**: Trial countdown accuracy - sync with server time
- **Low**: Responsive design for dashboard widgets

## Component Architecture

### Subscription Status Widget

**File**: `src/components/subscription/SubscriptionStatusWidget.tsx`

```tsx
import React from 'react';
import { Link } from 'react-router-dom';
import { Crown, Clock, AlertTriangle, ArrowUpRight } from 'lucide-react';
import { useSubscription } from '@/hooks/useSubscription';
import { PlanBadge } from './PlanBadge';
import { cn } from '@/lib/utils';

export function SubscriptionStatusWidget() {
  const { data: subscription, isLoading } = useSubscription();

  if (isLoading) {
    return (
      <div className="bg-white rounded-xl border p-6 animate-pulse">
        <div className="h-6 bg-gray-200 rounded w-1/3 mb-4" />
        <div className="h-4 bg-gray-200 rounded w-2/3" />
      </div>
    );
  }

  if (!subscription) {
    return null;
  }

  const isTrialing = subscription.status === 'trialing';
  const isCanceled = subscription.ends_at !== null;
  const trialDaysRemaining = isTrialing
    ? Math.ceil((new Date(subscription.trial_ends_at!).getTime() - Date.now()) / (1000 * 60 * 60 * 24))
    : 0;

  return (
    <div className="bg-white rounded-xl border p-6">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <Crown className="h-6 w-6 text-yellow-500" />
          <div>
            <h3 className="font-semibold text-gray-900">Your Plan</h3>
            <PlanBadge plan={subscription.plan} />
          </div>
        </div>
        <Link
          to="/subscription/plans"
          className="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-1"
        >
          Change Plan
          <ArrowUpRight className="h-4 w-4" />
        </Link>
      </div>

      {/* Trial Banner */}
      {isTrialing && (
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
          <div className="flex items-center gap-3">
            <Clock className="h-5 w-5 text-blue-600" />
            <div>
              <p className="text-sm font-medium text-blue-900">
                Trial ends in {trialDaysRemaining} day{trialDaysRemaining !== 1 ? 's' : ''}
              </p>
              <p className="text-xs text-blue-700">
                Add a payment method to continue after your trial
              </p>
            </div>
          </div>
          <Link
            to="/billing/payment-methods"
            className="mt-3 block text-center bg-blue-600 text-white text-sm font-medium py-2 px-4 rounded-lg hover:bg-blue-700"
          >
            Add Payment Method
          </Link>
        </div>
      )}

      {/* Cancellation Banner */}
      {isCanceled && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
          <div className="flex items-center gap-3">
            <AlertTriangle className="h-5 w-5 text-yellow-600" />
            <div>
              <p className="text-sm font-medium text-yellow-900">
                Subscription ending
              </p>
              <p className="text-xs text-yellow-700">
                Access ends on {new Date(subscription.ends_at!).toLocaleDateString()}
              </p>
            </div>
          </div>
          <button
            onClick={() => {/* TODO: Resume subscription */}}
            className="mt-3 w-full text-center bg-yellow-600 text-white text-sm font-medium py-2 px-4 rounded-lg hover:bg-yellow-700"
          >
            Resume Subscription
          </button>
        </div>
      )}

      {/* Billing Info */}
      <div className="text-sm text-gray-600 space-y-1">
        <p>
          <span className="font-medium">Billing cycle:</span>{' '}
          {subscription.billing_cycle === 'yearly' ? 'Annual' : 'Monthly'}
        </p>
        <p>
          <span className="font-medium">Next billing:</span>{' '}
          {new Date(subscription.current_period_end).toLocaleDateString()}
        </p>
      </div>
    </div>
  );
}
```

### Usage Metrics Widget

**File**: `src/components/subscription/UsageMetricsWidget.tsx`

```tsx
import React from 'react';
import { Link } from 'react-router-dom';
import { Calendar, Users, Briefcase, TrendingUp } from 'lucide-react';
import { useUsageMetrics } from '@/hooks/useSubscription';
import { UsageProgressBar } from './UsageProgressBar';

export function UsageMetricsWidget() {
  const { data: usage, isLoading } = useUsageMetrics();

  if (isLoading) {
    return (
      <div className="bg-white rounded-xl border p-6 animate-pulse">
        <div className="h-6 bg-gray-200 rounded w-1/3 mb-4" />
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-8 bg-gray-200 rounded" />
          ))}
        </div>
      </div>
    );
  }

  if (!usage) {
    return null;
  }

  const metrics = [
    {
      label: 'Reservations',
      icon: Calendar,
      current: usage.reservations.current,
      limit: usage.reservations.limit,
      percentage: usage.reservations.percentage,
    },
    {
      label: 'Team Members',
      icon: Users,
      current: usage.users.current,
      limit: usage.users.limit,
      percentage: usage.users.percentage,
    },
    {
      label: 'Services',
      icon: Briefcase,
      current: usage.services.current,
      limit: usage.services.limit,
      percentage: usage.services.percentage,
    },
  ];

  const showUpgradePrompt = metrics.some(
    (m) => m.limit !== 'unlimited' && m.percentage >= 80
  );

  return (
    <div className="bg-white rounded-xl border p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-semibold text-gray-900 flex items-center gap-2">
          <TrendingUp className="h-5 w-5 text-gray-400" />
          Usage This Month
        </h3>
      </div>

      <div className="space-y-4">
        {metrics.map((metric) => (
          <UsageProgressBar
            key={metric.label}
            icon={metric.icon}
            label={metric.label}
            current={metric.current}
            limit={metric.limit}
            percentage={metric.percentage}
          />
        ))}
      </div>

      {showUpgradePrompt && (
        <div className="mt-4 pt-4 border-t">
          <p className="text-sm text-gray-600 mb-2">
            Running low on limits? Upgrade for more.
          </p>
          <Link
            to="/subscription/plans"
            className="block text-center bg-blue-600 text-white text-sm font-medium py-2 px-4 rounded-lg hover:bg-blue-700"
          >
            View Upgrade Options
          </Link>
        </div>
      )}
    </div>
  );
}
```

### Usage Progress Bar

**File**: `src/components/subscription/UsageProgressBar.tsx`

```tsx
import React from 'react';
import { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

interface UsageProgressBarProps {
  icon: LucideIcon;
  label: string;
  current: number;
  limit: number | 'unlimited';
  percentage: number;
}

export function UsageProgressBar({
  icon: Icon,
  label,
  current,
  limit,
  percentage,
}: UsageProgressBarProps) {
  const isUnlimited = limit === 'unlimited';
  const isNearLimit = !isUnlimited && percentage >= 80;
  const isAtLimit = !isUnlimited && percentage >= 100;

  const progressColor = isAtLimit
    ? 'bg-red-500'
    : isNearLimit
    ? 'bg-yellow-500'
    : 'bg-blue-500';

  return (
    <div>
      <div className="flex items-center justify-between mb-1">
        <div className="flex items-center gap-2 text-sm text-gray-600">
          <Icon className="h-4 w-4" />
          <span>{label}</span>
        </div>
        <span className="text-sm font-medium text-gray-900">
          {current.toLocaleString()}
          {!isUnlimited && ` / ${(limit as number).toLocaleString()}`}
          {isUnlimited && ' (unlimited)'}
        </span>
      </div>

      {!isUnlimited && (
        <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
          <div
            className={cn('h-full rounded-full transition-all', progressColor)}
            style={{ width: `${Math.min(percentage, 100)}%` }}
          />
        </div>
      )}

      {isUnlimited && (
        <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
          <div className="h-full w-full bg-green-200" />
        </div>
      )}

      {isNearLimit && !isAtLimit && (
        <p className="text-xs text-yellow-600 mt-1">
          You've used {Math.round(percentage)}% of your limit
        </p>
      )}

      {isAtLimit && (
        <p className="text-xs text-red-600 mt-1">
          Limit reached! Upgrade to continue.
        </p>
      )}
    </div>
  );
}
```

### Plan Badge

**File**: `src/components/subscription/PlanBadge.tsx`

```tsx
import React from 'react';
import { Plan } from '@/types/subscription';
import { cn } from '@/lib/utils';

interface PlanBadgeProps {
  plan: Plan;
  size?: 'sm' | 'md';
}

const PLAN_COLORS: Record<string, string> = {
  free: 'bg-gray-100 text-gray-700',
  easy: 'bg-blue-100 text-blue-700',
  smart: 'bg-purple-100 text-purple-700',
  standard: 'bg-green-100 text-green-700',
  premium: 'bg-yellow-100 text-yellow-800',
};

export function PlanBadge({ plan, size = 'md' }: PlanBadgeProps) {
  const colorClass = PLAN_COLORS[plan.slug] || PLAN_COLORS.free;

  return (
    <span
      className={cn(
        'inline-flex items-center font-medium rounded-full',
        colorClass,
        size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-sm px-3 py-1'
      )}
    >
      {plan.name}
    </span>
  );
}
```

### Feature Gate Component

**File**: `src/components/subscription/FeatureGate.tsx`

```tsx
import React from 'react';
import { Lock, ArrowUpRight } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useFeatureAccess } from '@/hooks/useSubscription';

interface FeatureGateProps {
  feature: string;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

export function FeatureGate({ feature, children, fallback }: FeatureGateProps) {
  const { data: access, isLoading } = useFeatureAccess(feature);

  if (isLoading) {
    return <div className="animate-pulse bg-gray-100 rounded h-full" />;
  }

  if (access?.available) {
    return <>{children}</>;
  }

  if (fallback) {
    return <>{fallback}</>;
  }

  return (
    <div className="relative group">
      <div className="opacity-50 pointer-events-none blur-[1px]">
        {children}
      </div>
      <div className="absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm rounded-lg">
        <div className="text-center p-4">
          <Lock className="h-8 w-8 text-gray-400 mx-auto mb-2" />
          <p className="text-sm font-medium text-gray-700 mb-1">
            {access?.required_plan || 'Higher'} Plan Required
          </p>
          <Link
            to="/subscription/plans"
            className="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700"
          >
            Upgrade
            <ArrowUpRight className="h-3 w-3" />
          </Link>
        </div>
      </div>
    </div>
  );
}
```

### Header Plan Badge

**File**: `src/components/layout/HeaderPlanBadge.tsx`

```tsx
import React from 'react';
import { Link } from 'react-router-dom';
import { Clock, AlertCircle } from 'lucide-react';
import { useSubscription } from '@/hooks/useSubscription';
import { PlanBadge } from '../subscription/PlanBadge';

export function HeaderPlanBadge() {
  const { data: subscription, isLoading } = useSubscription();

  if (isLoading || !subscription) {
    return null;
  }

  const isTrialing = subscription.status === 'trialing';
  const trialDaysRemaining = isTrialing
    ? Math.ceil((new Date(subscription.trial_ends_at!).getTime() - Date.now()) / (1000 * 60 * 60 * 24))
    : 0;

  const hasPaymentIssue = subscription.status === 'past_due';

  return (
    <Link
      to="/subscription"
      className="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors"
    >
      <PlanBadge plan={subscription.plan} size="sm" />

      {isTrialing && (
        <span className="flex items-center gap-1 text-xs text-blue-600">
          <Clock className="h-3 w-3" />
          {trialDaysRemaining}d left
        </span>
      )}

      {hasPaymentIssue && (
        <span className="flex items-center gap-1 text-xs text-red-600">
          <AlertCircle className="h-3 w-3" />
          Payment issue
        </span>
      )}
    </Link>
  );
}
```

### Limit Reached Modal

**File**: `src/components/subscription/LimitReachedModal.tsx`

```tsx
import React from 'react';
import { Link } from 'react-router-dom';
import { X, AlertCircle, ArrowUpRight } from 'lucide-react';

interface LimitReachedModalProps {
  resource: 'reservations' | 'users' | 'services';
  onClose: () => void;
}

const RESOURCE_LABELS: Record<string, { title: string; description: string }> = {
  reservations: {
    title: 'Monthly Reservation Limit Reached',
    description: 'You have reached your monthly reservation limit. Upgrade your plan to create more reservations.',
  },
  users: {
    title: 'Team Member Limit Reached',
    description: 'You have reached your team member limit. Upgrade your plan to add more team members.',
  },
  services: {
    title: 'Service Limit Reached',
    description: 'You have reached your service limit. Upgrade your plan to add more services.',
  },
};

export function LimitReachedModal({ resource, onClose }: LimitReachedModalProps) {
  const content = RESOURCE_LABELS[resource];

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
              <AlertCircle className="h-5 w-5 text-red-600" />
            </div>
            <h2 className="text-lg font-bold text-gray-900">{content.title}</h2>
          </div>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <p className="text-gray-600 mb-6">{content.description}</p>

        <div className="space-y-3">
          <Link
            to="/subscription/plans"
            className="w-full flex items-center justify-center gap-2 bg-blue-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-700"
          >
            View Upgrade Options
            <ArrowUpRight className="h-4 w-4" />
          </Link>
          <button
            onClick={onClose}
            className="w-full py-3 px-4 rounded-lg font-medium text-gray-600 hover:bg-gray-100"
          >
            Maybe Later
          </button>
        </div>
      </div>
    </div>
  );
}
```

## API Integration

### Feature Access Hook

**File**: `src/hooks/useSubscription.ts` (additions)

```typescript
export function useFeatureAccess(feature: string) {
  return useQuery({
    queryKey: ['feature', feature],
    queryFn: () => api.get<{
      available: boolean;
      value: boolean | string;
      required_plan: string;
    }>(`/subscription/features/${feature}`),
  });
}

export function useAllFeatures() {
  return useQuery({
    queryKey: ['features'],
    queryFn: () => api.get<{ features: Record<string, any> }>('/subscription/features'),
  });
}
```

## Testing Strategy

### E2E Test
- `TestSubscriptionDashboard` covering usage display, trial countdown, upgrade prompts
- Verify: Metrics update correctly, modals work, feature gates function

### Manual Verification
- View dashboard with different plan levels
- Check trial countdown accuracy
- Trigger limit reached modal
- Test feature gate on locked features

## Implementation Steps

1. **Medium** - Create SubscriptionStatusWidget
2. **Medium** - Create UsageMetricsWidget
3. **Small** - Create UsageProgressBar component
4. **Small** - Create PlanBadge component
5. **Medium** - Create FeatureGate component
6. **Small** - Create HeaderPlanBadge component
7. **Medium** - Create LimitReachedModal
8. **Small** - Add feature access hooks
9. **Medium** - Integrate HeaderPlanBadge into main layout
10. **Medium** - Add dashboard widgets to main dashboard
11. **Medium** - Style and responsive design
12. **Medium** - Write component tests

## Cross-Task Dependencies

- **Depends on**: `backend_subscription_service.md`, `backend_usage_limit_enforcement.md`, `backend_feature_gating.md`
- **Blocks**: None
- **Parallel work**: Can work alongside `frontend_plan_selection.md`, `frontend_billing_page.md`
