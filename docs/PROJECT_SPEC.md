# NovaERP — Project Specification

## Purpose

NovaERP is a production-quality, open-architecture Enterprise Resource Planning platform built for **NovaTech Industries**, a fictional electronics manufacturing and distribution company. It demonstrates how a modern ERP system can be built on a clean, modular, maintainable foundation using current web technologies.

## Business Context — NovaTech Industries

NovaTech Industries designs, manufactures, and distributes consumer and industrial electronics. The company operates:

- A **manufacturing division** that produces circuit boards, electronic assemblies, and finished goods
- A **distribution network** supplying retailers, wholesalers, and direct enterprise customers
- A **procurement function** sourcing components globally
- A **finance and HR division** managing costs, payroll, and compliance

The ERP system must support the full operational lifecycle: from component procurement → manufacturing → quality control → sales → delivery → invoicing → accounting.

## Planned Modules

| Module | Description |
|---|---|
| **Organization** | Company structure, departments, locations |
| **HR & Payroll** | Employees, roles, contracts, payroll, leave |
| **CRM** | Leads, contacts, accounts, opportunities |
| **Sales** | Quotations, sales orders, invoices, receipts |
| **Purchasing** | Supplier management, purchase orders, receipts |
| **Inventory** | Stock management, warehouses, transfers, adjustments |
| **Manufacturing** | Production orders, routing, work centers |
| **Bill of Materials** | Component trees, variants, revisions |
| **Quality Control** | Inspection orders, criteria, non-conformances |
| **Accounting** | Chart of accounts, journal entries, AP/AR, financial reports |
| **Authentication & RBAC** | User accounts, roles, permissions, access control |
| **Notifications** | System alerts, user notifications, email integration |
| **Audit Logs** | Immutable record of all data changes |
| **Reports & Analytics** | Cross-module operational and financial reporting |

## High-Level Business Workflows

### Procure to Pay
Supplier → Purchase Order → Goods Receipt → Quality Inspection → Inventory → Supplier Invoice → Payment

### Make to Stock
BOM + Demand → Production Order → Component Consumption → Manufacturing → Quality Control → Finished Goods Inventory

### Order to Cash
Lead → Opportunity → Quotation → Sales Order → Delivery → Customer Invoice → Payment Receipt

### Record to Report
Transactions (all modules) → Journal Entries → Trial Balance → Financial Statements

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.5.9, Laravel 13.x |
| Authentication | Laravel Sanctum |
| ORM | Eloquent |
| Database | PostgreSQL 18.6 |
| Frontend | React 19, TypeScript, Vite |
| Styling | Tailwind CSS v4 |
| Routing | React Router v7 |
| Server State | TanStack Query v5 |
| Client State | Zustand v5 |
| HTTP Client | Axios |
| Testing | PHPUnit (backend) |
| Version Control | Git |
