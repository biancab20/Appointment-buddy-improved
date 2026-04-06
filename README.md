# Appointment Buddy Improved

Appointment Buddy Improved is the updated version of the old Appointment Buddy project.

## Main Updates Compared To The Old Project

- API-first architecture: backend now focuses on REST endpoints (no server-rendered HTML pages).
- Role-based security on endpoints with 3 roles: `admin`, `tutor`, `student`.
- Authentication upgraded to JWT access tokens + refresh tokens.
- Tutor ownership model added: tutors manage their own services and service timeslots.
- Stripe payment integration added for booking checkout flow.
  - Checkout session creation
  - Stripe webhook handling
  - Transaction tracking
- Vue.js frontend introduced (TypeScript + Vue Router + Pinia + Axios).
- Pagination and filtering added across key list endpoints (including admin overviews).
- Full Dockerized backend workflow kept for local development.

## Project Structure

- `backend/` - PHP REST API, Docker, auth, business logic, payment/webhooks.
- `frontend/` - Vue SPA for student, tutor, and admin dashboards.

## How To Run

### Backend

See [backend README](./backend/README.md).

### Frontend

See [frontend README](./frontend/README.md).

## Test Credentials (Seeded)

- Admin:
  - Email: `admin@example.com`
  - Password: `password`
- Tutor:
  - Email: `tutor@example.com`
  - Password: `password`

Students can register from the frontend.
