# BookEase – Project Instructions for Claude Code

## Project Overview
BookEase is a web-based booking and management system for **Labnasan Resort** (Basey, Samar).  
It replaces manual notebook + Messenger workflows with:

- Real-time availability and booking (prevents double-booking)
- Automated rate calculation
- Verified e-wallet payment (GCash / Maya via Philippine gateway + webhook)
- RAG-powered AI chatbot grounded only on live database records
- Staff & Admin dashboards with operational reports

Target users: Guests, Staff, Administrator (Owner).

This is a real production-oriented system for a thesis (ITP 414L Software Engineering). Quality, security, and polish matter.

## Tech Stack (Strict)
- PHP 8.2
- Laravel 12
- MySQL / MariaDB
- Blade templates
- Tailwind CSS 4 + Alpine.js
- Google Gemini API (for RAG chatbot)
- Philippine Payment Gateway (sandbox first – GCash & Maya)
- Vite for assets

Do **not** introduce Next.js, React, Vue, or other frameworks unless explicitly asked.

## Design System – Make It Feel Premium & Local

### Visual Identity
- Warm tropical / Filipino provincial resort aesthetic
- Primary colors: deep teal (#0F766E), warm sand (#F5E6C8), soft coral accents, cream backgrounds
- Avoid generic SaaS blue, purple gradients, and “AI starter kit” looks
- Use natural textures, soft shadows, and organic shapes where appropriate
- Typography: clean, readable, slightly elegant (prefer Inter or similar modern sans + a refined serif for headings if needed)

### Motion & 3D Effects (Very Important)
Apply generous, high-quality motion throughout the system. Prefer smoothness over minimalism.

Required effects:
- Page transitions (fade + slight upward slide)
- Card hover: lift (translateY + scale) + stronger shadow + subtle 3D tilt (perspective + rotateX/rotateY on mouse move if possible)
- Booking cards and cottage cards: 3D hover tilt + soft glow
- Buttons: smooth scale + shadow lift on hover, active press-down effect
- Modal / drawer: scale + fade with backdrop blur
- Form focus states: gentle glow and border color transition
- Loading states: elegant skeleton loaders or subtle pulse
- Success / error toasts: slide-in + fade with slight bounce
- Dashboard cards: staggered entrance animations
- Chatbot messages: smooth fade-up + slight scale
- Availability calendar: smooth date selection transitions
- Image galleries: gentle zoom + overlay on hover

Implementation preference:
- Use Tailwind transition classes heavily (`transition-all duration-300`, `ease-out`, etc.)
- Prefer Alpine.js or pure CSS for interactions
- Add `transform-style: preserve-3d` and perspective where 3D tilt is used
- Keep performance good (prefer `transform` and `opacity` for animations)

### UI Patterns
- Soft rounded corners (rounded-2xl / rounded-3xl for cards)
- Generous whitespace but not empty
- Clear visual hierarchy
- High-quality cottage images with overlay text on hover
- Status badges with soft colors and subtle animations
- Mobile-first responsive design that still feels premium on desktop

## Core Business Rules

### Availability & Booking
- Use **half-open date intervals** (check-out day is free for new check-in)
- Never allow double-booking of the same unit
- Single authoritative availability source = database
- Reservation lifecycle: Pending → Confirmed → Checked-in → Checked-out (and Cancelled / Rejected)
- Auto-calculate total cost based on nights × rate (and any extra fees defined)

### Payment
- Guests pay via Philippine e-wallet gateway (GCash / Maya)
- Never store e-wallet credentials
- Rely on cryptographically signed webhook for confirmation
- Issue digital receipt linked to the booking
- Support partial (down payment) and full payment
- Sandbox mode first

### AI Chatbot (Critical Constraints)
- Retrieval-Augmented Generation only
- Before every response, retrieve live data from the database (cottages, rates, availability, policies)
- Answer **only** from the retrieved grounding context
- If the answer is not in the context → politely direct the guest to contact the resort
- Chatbot must **never** create, modify, or cancel bookings
- Strong prompt-injection defenses:
  - Fixed system instruction
  - Fence user input as pure data
  - Treat all user messages as untrusted
- Log every chatbot interaction (question + answer + context used) for audit

### Roles & Access
- Guest
- Staff
- Administrator
Use Laravel middleware + policies for strict RBAC.

## Architecture Guidelines
- Clean Laravel structure (Controllers thin, logic in Services / Actions)
- Form Requests for validation
- Eloquent relationships properly defined
- Database migrations must be clear and complete
- Prefer queues for non-critical work (email, webhook processing if needed)
- Secure by default (CSRF, XSS protection, hashed passwords, rate limiting on chatbot & payment endpoints)

## Code Quality
- Follow Laravel best practices and PSR standards
- Meaningful names, clear comments only when necessary
- Prefer simple, readable solutions over clever ones
- Write tests for critical paths (booking conflict, payment webhook, chatbot grounding)
- After generating a feature, clean it up (Code Simplifier style)

## Anti-AI Design Rules (Strict)
Never produce:
- Generic blue/purple SaaS look
- Overused “eyebrow” labels + tracked uppercase text
- One random colored word in every heading
- Empty state illustrations that look stock
- Cards with no personality
- Flat, lifeless interfaces
- Overly perfect, sterile layouts

Always aim for:
- Warm, inviting, slightly organic resort feeling
- Clear visual hierarchy with personality
- Smooth, delightful micro-interactions
- Professional but not corporate

## Preferred Development Order
1. Project setup + authentication + roles
2. Cottage management (CRUD + images)
3. Availability engine + booking flow
4. Payment integration + webhook + receipts
5. Staff & Admin dashboards + reports
6. RAG chatbot (with grounding + logging + security)
7. Polish: animations, 3D effects, responsive refinements, loading states

## When Starting a New Feature
- First understand the exact requirement from this file
- Plan the database changes and routes
- Implement cleanly
- Add the required transitions and 3D/hover effects
- Make sure it feels premium and local to a Samar beach resort

---

You are building a real system for a real resort.  
Make it reliable, secure, beautiful, and pleasant to use.