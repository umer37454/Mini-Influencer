# MiniInfluencer Watchlist System

## Overview

MiniInfluencer is a Laravel + React based influencer watchlist system focused on backend engineering concepts such as:

* Queue-based background processing
* Retry classification
* Redis locking
* Circuit breaker protection
* Webhook security
* PostgreSQL query optimization
* Snapshot history tracking
* Health monitoring
* Concurrency safety

The system allows users to:

* Add YouTube creators to a watchlist
* Refresh creator metrics asynchronously
* Store historical snapshots
* View growth over time
* Prevent duplicate concurrent fetches
* Handle third-party API failures safely

---

# Tech Stack

## Backend

* Laravel 13
* PostgreSQL
* Redis
* Laravel Queues

## Frontend

* React
* TypeScript
* Inertia.js

---

# Project Setup

## 1. Clone Repository

```bash
git clone <repo-url>
cd miniinfluencer
```

---

# 2. Install Dependencies

## PHP dependencies

```bash
composer install
```

## Node dependencies

```bash
npm install
```

---

# 3. Environment Setup

Create `.env`

```bash
cp .env.example .env
```

Generate app key:

```bash
php artisan key:generate
```

---

# 4. PostgreSQL Setup

Create a PostgreSQL database.

Update `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=miniinfluencer
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

---

# 5. Redis Setup

Ensure Redis server is running.

Example:

```bash
redis-server
```

Update `.env`:

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

# 6. Run Migrations

```bash
php artisan migrate
```

---

# 7. Seed Database - Only if you want to test indexing

```bash
php artisan db:seed --class=ProfileSeeder
```

---

# 8. Start Laravel Server

```bash
php artisan serve
```

---

# 9. Start Queue Worker

Required for background jobs.

```bash
php artisan queue:work
```

Without this:

* jobs remain pending
* profile fetches never execute

---

# 10. Start Scheduler

Required for automatic stale-profile refreshes.

Run:

```bash
php artisan schedule:work
```

The scheduler dispatches background refresh jobs automatically.

---

# 11. Start Frontend

```bash
npm run dev
```

---

# YouTube API Setup

## 1. Create Google Cloud Project

Open:

[https://console.cloud.google.com/](https://console.cloud.google.com/)

---

## 2. Enable YouTube Data API v3

Search:

```text
YouTube Data API v3
```

Enable it.

---

# 3. Create API Key

Navigate:

```text
APIs & Services → Credentials
```

Create:

```text
API Key
```

---

# 4. Add API Key to `.env`

```env
YOUTUBE_API_KEY=your_api_key_here
```

---

# Webhook Secret Setup

Add:

```env
WEBHOOK_SECRET=super-secret-key
```

This secret is used for HMAC verification.

---

# Running Tests

```bash
php artisan test
```

---

# Important Commands

## Queue Worker

```bash
php artisan queue:work
```

## Scheduler

```bash
php artisan schedule:work
```

## Run Tests

```bash
php artisan test
```

## Clear Cache

```bash
php artisan optimize:clear
```

---

# Future Improvements

Potential improvements:

* Token bucket rate limiter
* Queue metrics dashboard
* Real webhook provider integration
* Distributed tracing
* Snapshot partitioning
* Horizontal queue scaling
* WebSocket live updates

---

# Notes

* All timestamps are stored in UTC.
* Timestamps are converted to IST only for rendering.
* Redis is used for locking, quota tracking, replay protection, and circuit breaker state.
* PostgreSQL is used for transactional consistency and time-series storage.

# System Architecture

```text
User Action
    ↓
Controller
    ↓
Dispatch Queue Job
    ↓
Redis Queue
    ↓
Queue Worker
    ↓
YouTube API
    ↓
Transaction:
  - Store Snapshot
  - Update Profile
    ↓
Frontend Refresh
```

---

# Features Implemented

## 1. Background Jobs

All third-party API calls happen inside `FetchProfileJob`.

No synchronous HTTP calls are made from controllers.

The controller immediately returns while the queue worker performs the expensive work asynchronously.

### Status Machine

```text
pending → fetching → fetched
pending → fetching → failed
```

---

# 2. Retry Classification

The job classifies errors intentionally.

| Error Type         | Classification | Behavior           |
| ------------------ | -------------- | ------------------ |
| 5xx                | Retriable      | Retry with backoff |
| 429                | Retriable      | Retry with backoff |
| Connection timeout | Retriable      | Retry              |
| 404                | Fatal          | Mark failed        |
| 401                | Fatal          | Mark failed        |
| Invalid payload    | Fatal          | No retry           |

### Retry Strategy

```php
public int $tries = 5;

public function backoff(): array
{
    return [60, 300, 900];
}
```

---

# 3. Redis Locking (Concurrency Safety)

The system prevents duplicate concurrent fetches for the same profile.

## Problem

Two queue workers may pick up the same job simultaneously.

Without protection:

* Duplicate HTTP requests
* Duplicate DB writes
* Wasted quota

## Solution

A Redis lock is used:

```php
Redis::set($lockKey, 1, 'EX', 120, 'NX');
```

### Why TTL matters

The lock expires automatically after 120 seconds.

This prevents deadlocks if:

* the worker crashes
* the process exits unexpectedly
* the lock is never manually released

### Flow

```text
Worker 1 acquires lock → proceeds
Worker 2 fails lock acquisition → exits
```

---

# 4. Circuit Breaker

## Problem

If the third-party API is down, hundreds of jobs may continuously retry.

This creates:

* retry storms
* queue overload
* quota waste

## Solution

After 10 consecutive API failures:

```text
Circuit opens for 2 minutes
```

During this period:

* jobs are deferred
* no new API calls are made

After 2 minutes:

* one request is allowed through
* success closes the circuit
* failure reopens it

## State Machine

```text
Closed
  ↓ (10 failures)
Open
  ↓ (2 min later)
Half Open
  ↓ success
Closed
```

---

# 5. Rate Limiting + Quota Tracking

The system tracks YouTube quota usage using Redis.

## Redis Key

```text
youtube_quota:YYYY-MM-DD
```

Example:

```text
youtube_quota:2026-05-18
```

The application refuses additional requests when usage reaches 90% of the daily limit.

This prevents exhausting the YouTube API quota.

---

# 6. Webhook Security

A simulated webhook endpoint was implemented.

```text
POST /webhooks/youtube
```

## Security Features

### HMAC Signature Verification

Requests are verified using:

```php
hash_hmac('sha256', payload, secret)
```

This prevents fake requests.

### Replay Protection

Webhook event IDs are stored in Redis.

Duplicate events are rejected.

### Async Processing

The webhook immediately dispatches a queue job instead of performing heavy work synchronously.

---

# 7. Database Design

## Tables

### profiles

Stores:

* username
* channel_id
* current metrics
* status
* last_refreshed_at

### profile_snapshots

Stores historical time-series data.

Relationship:

```text
Profile → hasMany → ProfileSnapshot
```

---

# 8. Transactional Integrity

Snapshot creation and profile updates occur inside a single DB transaction.

```php
DB::transaction(...)
```

This prevents partial writes.

---

# 9. PostgreSQL Optimization

## Composite Index

Added index:

```text
(status, created_at)
```

to optimize:

* filtering
* sorting
* pagination

## EXPLAIN ANALYZE Result

Before indexing:

```text
Seq Scan
```

After indexing:

```text
Index Scan Backward using profiles_status_created_at_index
```

Query execution improved significantly.

---

# 10. N+1 Query Prevention

The application uses eager loading:

```php
->with('latestSnapshot')
```

This prevents:

```text
1 + N query explosions
```

Laravel Debugbar was used to verify query counts.

---

# 11. Health Endpoint

Endpoint:

```text
GET /healthz
```

Checks:

* PostgreSQL connectivity
* Redis connectivity

Returns:

```json
{
  "status": "ok"
}
```

---

# 12. Automated Tests

Implemented tests:

* Queue dispatch test
* Concurrency test
* Webhook signature test
* Replay protection test

## Concurrency Test

Ensures:

```text
Only one HTTP request occurs
for simultaneous jobs
```

---

