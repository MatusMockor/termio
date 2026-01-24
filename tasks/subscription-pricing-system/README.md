# Subscription Pricing System - Implementation Tasks

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Target Release**: Q2 2026
**Created**: 2026-01-23

## Overview

This folder contains implementation tasks for the Termio subscription pricing system. The system implements a 5-tier subscription model (FREE, EASY, SMART, STANDARD, PREMIUM) with Stripe integration via Laravel Cashier.

## Task Dependencies Graph

```
database_schema.md
    |
    v
backend_stripe_integration.md
    |
    +---> backend_subscription_service.md
    |         |
    |         +---> backend_usage_limit_enforcement.md
    |         |         |
    |         |         +---> backend_feature_gating.md
    |         |
    |         +---> backend_billing_invoicing.md
    |                   |
    |                   +---> backend_webhook_handling.md
    |
    +---> backend_plan_management.md
              |
              +---> admin_plan_management.md

frontend_plan_selection.md (depends on: backend_subscription_service.md)
    |
    v
frontend_subscription_dashboard.md (depends on: backend_usage_limit_enforcement.md)
    |
    v
frontend_billing_page.md (depends on: backend_billing_invoicing.md)

backend_email_notifications.md (depends on: backend_subscription_service.md, backend_billing_invoicing.md)

testing_subscription_system.md (depends on: all backend tasks)
```

## Task Files

| File | Category | Status | Complexity | Description |
|------|----------|--------|------------|-------------|
| `database_schema.md` | Database | Not Started | Large | Database schema and migrations |
| `backend_stripe_integration.md` | Backend | Not Started | Medium | Laravel Cashier + Stripe setup |
| `backend_subscription_service.md` | Backend | Not Started | Large | Subscription service layer |
| `backend_usage_limit_enforcement.md` | Backend | Not Started | Medium | Usage tracking and limits |
| `backend_feature_gating.md` | Backend | Not Started | Medium | Feature access control |
| `backend_billing_invoicing.md` | Backend | Not Started | Large | Billing automation and invoices |
| `backend_webhook_handling.md` | Backend | Not Started | Medium | Stripe webhook processing |
| `backend_plan_management.md` | Backend | Not Started | Medium | Plan CRUD operations |
| `backend_email_notifications.md` | Backend | Not Started | Medium | Subscription email notifications |
| `frontend_plan_selection.md` | Frontend | Not Started | Medium | Plan comparison and selection UI |
| `frontend_subscription_dashboard.md` | Frontend | Not Started | Medium | Subscription status dashboard |
| `frontend_billing_page.md` | Frontend | Not Started | Medium | Billing history and payment methods |
| `admin_plan_management.md` | Backend/Admin | Not Started | Medium | Admin panel for plans |
| `testing_subscription_system.md` | Testing | Not Started | Large | Comprehensive test suite |

## Implementation Order (Recommended)

### Phase 1: Foundation (Week 1-2)
1. `database_schema.md` - Database tables and migrations
2. `backend_stripe_integration.md` - Stripe/Cashier setup

### Phase 2: Core Backend (Week 3-4)
3. `backend_subscription_service.md` - Subscription management
4. `backend_plan_management.md` - Plan CRUD
5. `backend_usage_limit_enforcement.md` - Usage tracking

### Phase 3: Billing (Week 5-6)
6. `backend_billing_invoicing.md` - Invoices and payments
7. `backend_webhook_handling.md` - Stripe webhooks
8. `backend_feature_gating.md` - Feature access control

### Phase 4: Frontend (Week 7-8)
9. `frontend_plan_selection.md` - Plan selection UI
10. `frontend_subscription_dashboard.md` - Dashboard widgets
11. `frontend_billing_page.md` - Billing management

### Phase 5: Notifications & Admin (Week 9-10)
12. `backend_email_notifications.md` - Email system
13. `admin_plan_management.md` - Admin panel

### Phase 6: Testing & QA (Week 11-12)
14. `testing_subscription_system.md` - Full test suite

## Key Technical Decisions

- **Payment Gateway**: Stripe via Laravel Cashier v15.x
- **Architecture**: Actions, Services, Repositories pattern per CLAUDE.md
- **VAT Handling**: EU-compliant with VIES validation
- **Invoice Generation**: DomPDF for PDF generation
- **Usage Reset**: Monthly on 1st of each month
- **Feature Gates**: Centralized SubscriptionService

## Related PRDs

- `prds/2026-01-subscription-pricing-system.md` (source)
- `prds/2026-01-booking-saas-design-phase.md` (UI patterns)
- `prds/2026-01-booking-anti-spam-protection.md` (rate limiting integration)
