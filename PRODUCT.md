# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Guests (clients):** Filipino families, barkadas and couples planning a day trip or overnight stay at Labnasan Resort in Basey, Samar. They usually use a phone, often on mobile data, and pay with GCash or Maya. They want to know quickly which cottages are free, what it costs, and how to reserve without messaging the resort back and forth.
- **Front-desk staff:** resort employees who approve or reject requests, check guests in and out, and record cash payments. They work at the desk, often on a laptop or phone, and come back to the queue many times a day.
- **Owner (admin):** manages cottages and rates, staff accounts, reports, and reviews the chatbot audit log.

## Product Purpose

BookEase replaces manual reservations, payment confirmation, and repeated inquiries with one web system for Labnasan Resort. It succeeds when nobody double-books, every e-wallet payment is verified by the gateway, and guests get accurate answers taken from live resort data. It is a thesis project (ITP 414L Software Engineering) that is meant to be used for real.

## Positioning

It is the resort's own booking desk, not an aggregator. Availability is live per cottage, a down payment goes through GCash/Maya with a signed-webhook confirmation and an e-receipt, and an assistant answers only from the resort's own records.

## Operating Context

- Check-in is 2:00 PM and check-out 12:00 NN; the same day can be a changeover.
- A booking holds its dates while it is pending, confirmed or checked in.
- Guests sign in at `/login`; staff and the owner sign in at `/staff/login`.

## Capabilities and Constraints

- The stack is Laravel 12 with Blade, Tailwind and Alpine.js. No React, Vue, Livewire or Inertia, and no heavy JS animation libraries.
- Pages must be mobile-first and responsive.
- Payments stay in sandbox mode, and wallet credentials are never stored.
- The chatbot never invents rates, availability or policies.
- Collect only the data that is needed (Data Privacy Act).
- CLAUDE.md requires heavy transitions and 3D effects: card tilt and lift, staggered reveals, modal scale/fade, page transitions, and a floating chatbot entrance.

## Brand Commitments

- The names are "BookEase" (the system) and "Labnasan Resort, Basey, Samar".
- CLAUDE.md requires a warm coastal palette (soft sand, deep teal, ocean blue, coral accents, creamy whites), natural texture, distinctive non-generic typography, and no purple gradients, overused glassmorphism or cookie-cutter AI layouts.
- The user said on 2026-11-01 that the first UI looked "cartoony" because of the flat drawings, rounded/pill shapes, the chunky headline font, and the wave dividers with bright coral. They want it refined, and they want the landing page "much more amazing" with 3D transitions and effects.

## Evidence on Hand

- There are no real resort photos yet. Stock beach photos are used as clearly marked placeholders until the owner supplies real ones; they can be swapped through the admin cottage form.
- The cottages in the seeder are "[Sample]" placeholders. Real units and rates are pending from the owner.
- Do not invent reviews, ratings, awards, guest counts, amenities, or prices that the database does not contain.

## Product Principles

1. Correct availability and verified payments come before visuals.
2. Show real, live data and never decorate with fake claims.
3. Make the next step obvious for a guest on a phone.
4. Staff screens must be calm and quick to scan, because they are used many times a day.
