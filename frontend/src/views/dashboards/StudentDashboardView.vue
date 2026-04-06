<template>
  <main class="dashboard-page">
    <section class="dashboard-card">
      <p class="role">Student Dashboard</p>
      <h1>Welcome, student.</h1>
      <p>
        This is your dashboard placeholder. In the next step we can add upcoming sessions, booked
        appointments, and quick actions.
      </p>
    </section>
  </main>
</template>

<style scoped>
.dashboard-page {
  display: grid;
  min-height: 72vh;
  place-items: center;
}

.dashboard-card {
  background: #fff;
  border: 1px solid #ebdccd;
  border-radius: 18px;
  box-shadow: 0 14px 34px rgba(15, 51, 65, 0.08);
  max-width: 760px;
  padding: 1.4rem;
  width: 100%;
}

.role {
  color: #c57632;
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  margin-bottom: 0.45rem;
  text-transform: uppercase;
}

h1 {
  color: #122933;
  font-size: clamp(1.4rem, 4vw, 2rem);
  font-weight: 800;
  margin-bottom: 0.5rem;
}

p {
  color: #53646e;
}
</style>