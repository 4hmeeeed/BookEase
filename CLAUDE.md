# BookEase – Project Instructions

## Project Overview
BookEase is a web-based booking and management system with AI chatbot and e-wallet payment for Labnasan Resort (Basey, Samar). Built as a thesis project (ITP 414L Software Engineering).

**Goal:** Replace manual reservation, payment, and inquiry processes with a modern, reliable system that prevents double-booking, verifies e-wallet payments, and answers guest questions accurately from live resort data.

## Tech Stack (Strict)
- PHP 8.2
- Laravel 12
- MySQL / MariaDB
- Blade + Tailwind CSS + Alpine.js
- Laravel Breeze (Blade stack) for authentication
- Google Gemini API (RAG chatbot)
- Philippine payment gateway (GCash + Maya) – sandbox first
- No React, Vue, Livewire, or Inertia unless explicitly requested later

## Design System (Very Important)
Create a warm, tropical, beach-resort aesthetic that does **not** look AI-generated.

### Visual Direction
- Warm coastal palette: soft sand, deep teal, ocean blue, coral accents, creamy whites
- Natural textures and subtle grain where appropriate
- Clean but distinctive typography (avoid generic Inter / system fonts)
- Generous whitespace mixed with intentional density
- Soft shadows and layered depth

### Transitions & 3D Effects (Must implement heavily)
- Card hover: 3D tilt + lift (perspective + rotateX/rotateY + translateY)
- Smooth page transitions and section reveals
- Staggered entrance animations on lists and cards
- Button press feedback (scale + shadow)
- Modal scale + fade transitions
- Smooth availability calendar interactions
- Micro-interactions on form inputs and status badges
- Floating chatbot widget with soft entrance animation
- Use Tailwind + Alpine.js + pure CSS for all effects (no heavy JS libraries)

Avoid generic purple gradients, overused glassmorphism, and cookie-cutter AI layouts.

## Core Business Rules
1. **Availability uses half-open date intervals**
   - A booking holds dates from check_in (inclusive) to check_out (exclusive)
   - Same-day changeover is allowed (12:00 NN checkout → 2:00 PM check-in)

2. **Blocking statuses:** pending, confirmed, checked_in
   - rejected, cancelled, checked_out release the dates

3. **Payment**
   - Only accept GCash and Maya through the payment gateway
   - Never store wallet credentials
   - Confirm payments only via signed webhook/callback
   - Support down payment + balance
   - Issue electronic receipt after successful payment

4. **Roles (RBAC)**
   - Guest
   - Staff
   - Admin (Owner)
   - Strict middleware separation

5. **Chatbot (RAG)**
   - Answers **only** from live database records (cottages, rates, availability, policies)
   - Never invent rates, availability, or policies
   - If answer is not in the context → politely redirect to contact the resort
   - Cannot create or modify bookings
   - Log every inquiry (question + context + answer)
   - Rate limit requests
   - Strong prompt injection defenses

## Key Modules
1. Authentication + Role-based access
2. Cottage browsing & filtering
3. Availability & pricing engine
4. Reservation submission
5. E-wallet payment + verification
6. Booking lifecycle management (approve/reject/check-in/check-out)
7. Cottage & staff management
8. AI Inquiry Chatbot (RAG)
9. Chatbot audit logging
10. Reports & dashboard

## Coding Standards
- Prefer Laravel conventions and `php artisan make:` commands
- Use Form Requests for validation
- Keep controllers thin – put business logic in Services
- Use Eloquent relationships properly
- Write feature tests for availability and payment logic
- Use `bcmath` for all money calculations (no floating point)
- Keep the design system consistent across all pages

## Development Order
1. Authentication + Roles + basic layout
2. Cottages CRUD + seeder
3. Availability service + tests
4. Booking flow
5. Payment integration (sandbox)
6. Chatbot (RAG)
7. Admin dashboard & reports
8. Polish transitions, 3D effects, and responsiveness

## Important Constraints
- Responsive (mobile-first)
- No offline mode
- Payment stays in sandbox until explicitly moved to live
- Chatbot must never hallucinate resort data
- Follow Data Privacy Act principles (collect only necessary data)

Always read this file before starting any major task.
When in doubt, prioritize correctness of availability logic and payment verification over visual features.