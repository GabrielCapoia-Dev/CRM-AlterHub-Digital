---
name: filamentphp-senior-frontend
description: Senior standards for FilamentPHP v5 frontend work in Laravel 12+, covering Panels, Resources, Forms, Tables, Actions, Infolists, Widgets, Livewire v4, Alpine.js, and Tailwind CSS v4. Use when Codex needs to build, review, refactor, or design Filament admin interfaces, dashboards, resource pages, form schemas, table schemas, panel theming, or related Laravel UI architecture with strong Eloquent, authorization, UX, and performance discipline. Also use for pedidos em portugues sobre Filament, painel admin, formularios, tabelas, widgets, dashboard, recursos, tema, UX, performance, policies, e Eloquent.
---

# FilamentPHP Senior Frontend

## Overview

Use Filament-native solutions first. Prefer clear architecture, reusable schemas, Eloquent-driven data access, strong typing, and concise UX over clever workarounds.

Treat this skill as an implementation standard for Filament admin UI work. If the repository already establishes a different project convention, follow the repository while preserving the spirit of these rules.

## Execution Flow

1. Identify the domain model, its relations, validation rules, and authorization boundary before writing UI code.
2. Choose the smallest native Filament primitive that solves the task:
   - Use a `Resource` for CRUD-centric flows.
   - Use a resource page before creating a fully custom page.
   - Use a `Widget` for dashboard or summary surfaces.
   - Use an `Action` for record-level or bulk interactions.
3. Keep business logic out of `Resource`, `Page`, `Widget`, and schema closures. Move it to model methods, action classes, services, jobs, or observers.
4. Prefer Eloquent relations, scopes, `with()`, `withCount()`, `withSum()`, `when()`, `firstOrCreate()`, `updateOrCreate()`, and `upsert()` over manual branching or raw SQL.
5. Apply authorization through Laravel policies and let Filament consume them naturally.
6. Add interactivity with Filament state tools first, then Alpine.js for lightweight behavior. Avoid custom JavaScript unless the native stack cannot solve the problem.
7. Review UX, notifications, eager loading, and repeated UI fragments before considering the task complete.

## Default Response Shape

When solving a task with this skill:

1. Name the Filament component that best fits the problem.
2. Call out required support pieces such as model relations, policy, migration, enum, service, observer, or job.
3. Implement the full working code path when possible instead of leaving partial snippets.
4. Mention important performance, authorization, or UX considerations when they materially affect the solution.

## Component Selection

### Resources

- Keep methods in canonical order: `form()`, `table()`, `getRelations()`, `getPages()`, then helpers.
- Keep resource classes thin. Delegate domain behavior.
- Prefer enums over string status fields when the project supports them.

### Forms

- Group fields semantically with `Section`, `Grid`, and clear labels.
- Use inline field rules for simple validation and dedicated request or domain validation for complex flows.
- Use `live()` only when a reactive dependency genuinely requires it.
- Prefer `hidden()`, `visible()`, `options()`, and state hooks over broad reactivity.
- Add `helperText()` and realistic `placeholder()` values for complex inputs.

### Tables

- Define columns, filters, row actions, and bulk actions intentionally.
- Use `searchable()` and `sortable()` only where they improve the user experience.
- Default to descending `created_at` sorting for time-based lists unless the domain suggests otherwise.
- Eager load related data used by visible columns and filters.

### Actions

- Use Filament actions for confirmations, modals, and record operations.
- Require confirmation for destructive or irreversible work.
- Extract complex action behavior into dedicated classes or domain methods.
- Send user feedback with Filament notifications.

### Widgets

- Use `StatsOverviewWidget` for KPI summaries.
- Use `ChartWidget` for visual trends.
- Use `TableWidget` for supporting lists.
- Set widget ordering with `protected static ?int $sort`.
- Control layout with `columnSpan` and defer expensive loading when needed.

## Frontend Standards

- Prefer Tailwind utility composition in templates over custom CSS.
- Use Filament semantic colors and panel theme APIs before introducing arbitrary tokens.
- Avoid `tailwind.config.js` assumptions when the project is on Tailwind CSS v4.
- Reuse Blade components or extracted schema methods when a UI pattern repeats more than twice.
- Keep labels descriptive, breadcrumbs readable, and destructive actions clearly marked.

## Data And Performance

- Prevent N+1 queries in list and dashboard views.
- Load only the relations required by the current surface.
- Add database indexes for fields that become heavy search or sort targets when relevant to the task.
- Queue heavy exports, mail flows, and report generation.
- Cache or defer expensive dashboard queries where appropriate.

## Review Checklist

Before finishing, verify:

- The chosen Filament primitive is native and appropriate.
- Business logic is not trapped inside UI closures.
- Validation, authorization, and notifications are present where needed.
- Queries use eager loading and sensible filters.
- Repeated schema or Blade blocks are extracted.
- The UX is clear for labels, helper text, confirmation, and feedback.

## Extended Reference

Read [references/filament-standards.md](references/filament-standards.md) when you need the longer version of these conventions, including anti-patterns, Tailwind v4 notes, and architecture guidance for more complex tasks.
