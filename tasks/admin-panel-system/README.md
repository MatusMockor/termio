# Admin Panel / Platform Management System - Implementation Tasks

**PRD Source**: `prds/2026-01-admin-panel-system.md`
**Target Release**: Q2 2026
**Created**: 2026-01-25

## Overview

This folder contains implementation tasks for the Termio admin panel system. The system enables platform owners (super admins) to manage tenants, users, subscriptions, revenue, and platform settings across the entire multi-tenant SaaS platform.

## Task Dependencies Graph

```
database_schema.md
    |
    v
backend_admin_middleware.md
    |
    +---> backend_dashboard.md
    |         |
    |         +---> frontend_admin_integration.md (Dashboard)
    |
    +---> backend_tenant_management.md
    |         |
    |         +---> backend_impersonation.md
    |         |         |
    |         |         +---> frontend_admin_integration.md (Tenants)
    |
    +---> backend_user_management.md
    |         |
    |         +---> frontend_admin_integration.md (Users)
    |
    +---> backend_subscription_management.md
    |         |
    |         +---> frontend_admin_integration.md (Subscriptions)
    |
    +---> backend_revenue_reporting.md
    |         |
    |         +---> frontend_admin_integration.md (Revenue)
    |
    +---> backend_settings_feature_flags.md
    |         |
    |         +---> frontend_admin_integration.md (Settings)
    |
    +---> backend_audit_logging.md
              |
              +---> (used by all backend tasks)

testing_admin_system.md (depends on: all backend tasks)
```

## Task Files

| File | Category | Status | Complexity | Description |
|------|----------|--------|------------|-------------|
| `database_schema.md` | Database | Not Started | Large | 4 new tables + tenant modifications |
| `backend_admin_middleware.md` | Backend | Not Started | Small | Admin authentication middleware |
| `backend_dashboard.md` | Backend | Not Started | Large | Dashboard metrics and calculations |
| `backend_tenant_management.md` | Backend | Not Started | Large | Tenant CRUD and management |
| `backend_user_management.md` | Backend | Not Started | Medium | User management across tenants |
| `backend_subscription_management.md` | Backend | Not Started | Large | Subscription lifecycle management |
| `backend_revenue_reporting.md` | Backend | Not Started | Large | Revenue metrics and reporting |
| `backend_impersonation.md` | Backend | Not Started | Medium | Impersonation session management |
| `backend_settings_feature_flags.md` | Backend | Not Started | Medium | Platform settings and feature flags |
| `backend_audit_logging.md` | Backend | Not Started | Medium | Audit trail for admin actions |
| `frontend_admin_integration.md` | Frontend | Not Started | Large | Connect admin pages to real API |
| `testing_admin_system.md` | Testing | Not Started | Large | Comprehensive test suite |

## Implementation Order (Recommended)

### Phase 1: Foundation (Week 1)
1. `database_schema.md` - Database tables and migrations
2. `backend_admin_middleware.md` - Authentication and authorization
3. `backend_audit_logging.md` - Audit trail foundation

### Phase 2: Core Admin Features (Week 2-3)
4. `backend_dashboard.md` - Dashboard metrics
5. `backend_tenant_management.md` - Tenant CRUD
6. `backend_user_management.md` - User management
7. `backend_subscription_management.md` - Subscription operations

### Phase 3: Advanced Features (Week 4-5)
8. `backend_revenue_reporting.md` - Financial reporting
9. `backend_impersonation.md` - Impersonation system
10. `backend_settings_feature_flags.md` - Platform settings

### Phase 4: Frontend Integration (Week 6)
11. `frontend_admin_integration.md` - Replace mock data with API

### Phase 5: Testing & QA (Week 7)
12. `testing_admin_system.md` - Full test suite

## Key Technical Decisions

- **Authentication**: Simple boolean `is_admin` flag on users table (no complex RBAC in Phase 1)
- **Architecture**: Actions, Services, Repositories pattern per CLAUDE.md
- **Audit Logging**: 100% coverage of admin actions with 2-year retention
- **Soft Deletes**: All deletions are soft (30-day recovery period)
- **Caching**: Redis cache for dashboard metrics (30-second TTL)
- **Exports**: CSV format with background jobs for large datasets
- **Security**: Admin middleware on all `/api/admin/*` routes, HTTPS only
- **Session Management**: 4-hour timeout for admin sessions

## Existing Components (To Integrate)

**Backend**:
- `app/Http/Controllers/Admin/PlanController.php` - Fully implemented plan CRUD
- `app/Models/User.php` - Has `is_admin` field and `isAdmin()` method
- `app/Models/Tenant.php` - Base tenant model (needs `is_active` and soft delete)

**Frontend** (all with mock data):
- `src/pages/admin/AdminDashboardPage.tsx` - Dashboard with stats, charts, activity
- `src/pages/admin/TenantsPage.tsx` - Tenant list with CRUD modals
- `src/pages/admin/UsersPage.tsx` - User management
- `src/pages/admin/SubscriptionsPage.tsx` - Subscription management
- `src/pages/admin/PlansPage.tsx` - Plan management (connect to existing controller)
- `src/pages/admin/RevenuePage.tsx` - Revenue dashboard
- `src/pages/admin/SettingsPage.tsx` - Platform settings

## New Backend Components (To Create)

**Controllers**:
- `Admin/DashboardController.php` - Dashboard metrics
- `Admin/TenantController.php` - Tenant management
- `Admin/UserController.php` - User management
- `Admin/SubscriptionController.php` - Subscription lifecycle
- `Admin/RevenueController.php` - Financial reporting
- `Admin/ImpersonationController.php` - Impersonation sessions
- `Admin/SettingsController.php` - Platform settings
- `Admin/AuditLogController.php` - Audit log viewing

**Models**:
- `AuditLog.php` - Audit trail entries
- `FeatureFlag.php` - Platform feature flags
- `PlatformSetting.php` - Global settings (key-value)
- `ImpersonationSession.php` - Impersonation tracking

**Services**:
- `Admin/DashboardService.php` - Metrics calculation
- `Admin/TenantManagementService.php` - Tenant operations
- `Admin/SubscriptionManagementService.php` - Subscription operations
- `Admin/RevenueCalculationService.php` - MRR, ARR, churn
- `Admin/AuditLogService.php` - Logging logic
- `Admin/ImpersonationService.php` - Session management
- `Admin/ExportService.php` - CSV exports

**Repositories**:
- `Admin/TenantRepository.php` - Tenant queries
- `Admin/SubscriptionRepository.php` - Subscription queries
- `Admin/AuditLogRepository.php` - Audit log queries

**Middleware**:
- `AdminMiddleware.php` - Check `is_admin` flag
- `LogAdminAction.php` - Auto-log admin actions

**Jobs**:
- `Admin/ExportTransactionsJob.php` - Background CSV export
- `Admin/ExportMrrReportJob.php` - Background MRR export
- `Admin/ArchiveAuditLogsJob.php` - Log rotation
- `Admin/PermanentlyDeleteTenantsJob.php` - Hard delete after 30 days

## API Endpoints Summary

### Dashboard
- `GET /api/admin/dashboard`

### Tenants
- `GET /api/admin/tenants`
- `GET /api/admin/tenants/{id}`
- `POST /api/admin/tenants`
- `PATCH /api/admin/tenants/{id}`
- `DELETE /api/admin/tenants/{id}`
- `POST /api/admin/tenants/{id}/restore`
- `POST /api/admin/tenants/{id}/suspend`
- `POST /api/admin/tenants/{id}/activate`

### Users
- `GET /api/admin/users`
- `GET /api/admin/users/{id}`
- `PATCH /api/admin/users/{id}`
- `DELETE /api/admin/users/{id}`
- `POST /api/admin/users/{id}/suspend`
- `POST /api/admin/users/{id}/activate`
- `POST /api/admin/users/{id}/reset-password`

### Subscriptions
- `GET /api/admin/subscriptions`
- `GET /api/admin/subscriptions/{id}`
- `POST /api/admin/subscriptions/{id}/cancel`
- `POST /api/admin/subscriptions/{id}/extend-trial`
- `POST /api/admin/subscriptions/{id}/retry-payment`
- `POST /api/admin/subscriptions/{id}/mark-paid`

### Revenue
- `GET /api/admin/revenue/metrics`
- `GET /api/admin/revenue/chart`
- `GET /api/admin/revenue/breakdown`
- `GET /api/admin/revenue/transactions`
- `GET /api/admin/revenue/failed-payments`
- `POST /api/admin/revenue/export/transactions`
- `POST /api/admin/revenue/export/mrr`

### Impersonation
- `POST /api/admin/impersonate/{user_id}`
- `POST /api/admin/impersonate/exit`

### Settings
- `GET /api/admin/settings`
- `PATCH /api/admin/settings`
- `POST /api/admin/settings/stripe/test`
- `POST /api/admin/settings/email/test`

### Feature Flags
- `GET /api/admin/feature-flags`
- `PATCH /api/admin/feature-flags/{id}`

### Audit Log
- `GET /api/admin/audit-log`
- `POST /api/admin/audit-log/export`

## Related PRDs

- `prds/2026-01-admin-panel-system.md` (source)
- `prds/2026-01-subscription-pricing-system.md` (dependency)
- `prds/2026-01-booking-saas-design-phase.md` (UI patterns)

## Success Metrics

- Admin task completion: < 5 clicks
- Dashboard load time: < 2 seconds
- Tenant issue resolution: < 5 minutes
- Failed payment recovery: > 60%
- Audit log coverage: 100%
