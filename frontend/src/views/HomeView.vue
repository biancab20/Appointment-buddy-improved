<script setup lang="ts">
import { RouterLink } from 'vue-router'

import academicCapImage from '@/assets/images/academic-cap.png'
import bookImage from '@/assets/images/book.png'
import girlImage from '@/assets/images/girl_sitting_image.svg'
import teacherImage from '@/assets/images/teacher.png'

const featureCards = [
  {
    title: 'A Wide Range of Subjects',
    description:
      'From Math and Economics to Languages and Academic Writing, choose from tutoring services that match your goals.',
    image: bookImage,
    imageAlt: 'Book icon',
  },
  {
    title: 'Personalized Support',
    description:
      'Every session is focused on your learning pace, with one-to-one guidance that helps you understand the material clearly.',
    image: teacherImage,
    imageAlt: 'Teacher icon',
  },
  {
    title: 'Made for Students Like You',
    description:
      'Learn from tutors who know the same course challenges, with practical explanations and study-ready advice.',
    image: academicCapImage,
    imageAlt: 'Academic cap icon',
  },
]
</script>

<template>
  <main class="home-page">
    <section class="hero">
      <div class="hero-image-wrap">
        <img :src="girlImage" alt="Student learning with tutoring support" class="hero-image" />
      </div>

      <div class="hero-content">
        <p class="hero-tag">Personal Tutoring Platform</p>
        <h1>Customized Tutoring for Every Student</h1>
        <p class="hero-text">
          Schedule one-to-one tutoring sessions designed to help you master any subject. Fast booking,
          flexible times, and personalized support all in one place.
        </p>

        <div class="hero-actions">
          <RouterLink to="/about" class="btn btn-primary">Book now</RouterLink>
          <a href="#benefits" class="btn btn-ghost">Why Appointment Buddy</a>
        </div>
      </div>
    </section>

    <section class="pitch">
      <div class="pitch-title">Your Success, Supported at Every Step</div>
      <p class="pitch-text">
        Appointment Buddy connects students with academic support when they need it most. Whether you are
        preparing for exams, catching up on assignments, or strengthening your understanding, we make it
        easy to find the right tutor.
      </p>
    </section>

    <section id="benefits" class="benefits">
      <div class="benefits-head">
        <h2>Why book with Appointment Buddy?</h2>
        <p>Flexible tutoring options, tailored support, and relatable coaches in one simple platform.</p>
      </div>

      <div class="benefit-grid">
        <article v-for="card in featureCards" :key="card.title" class="benefit-card">
          <img :src="card.image" :alt="card.imageAlt" class="benefit-icon" />
          <h3>{{ card.title }}</h3>
          <p>{{ card.description }}</p>
        </article>
      </div>
    </section>
  </main>
</template>

<style scoped>
.home-page {
  display: grid;
  gap: 3rem;
}

.hero {
  align-items: center;
  background:
    radial-gradient(circle at 90% 10%, rgba(197, 118, 50, 0.16), transparent 48%),
    linear-gradient(145deg, #ffffff, #fff5ea);
  border: 1px solid rgba(15, 51, 65, 0.1);
  border-radius: 28px;
  display: grid;
  gap: 1.5rem;
  grid-template-columns: 1fr;
  overflow: hidden;
  padding: 1.5rem;
}

.hero-image-wrap {
  display: flex;
  justify-content: center;
}

.hero-image {
  max-height: 360px;
  width: min(100%, 420px);
}

.hero-content {
  display: grid;
  gap: 0.95rem;
}

.hero-tag {
  color: #a35f2d;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.15em;
  text-transform: uppercase;
}

h1 {
  color: #0f3341;
  font-size: clamp(1.8rem, 5vw, 2.8rem);
  line-height: 1.15;
}

.hero-text {
  color: #364650;
  font-size: clamp(1rem, 2.2vw, 1.15rem);
  max-width: 65ch;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.btn {
  border-radius: 10px;
  font-weight: 700;
  padding: 0.7rem 1.2rem;
  text-decoration: none;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    background-color 0.2s ease;
}

.btn:hover {
  transform: translateY(-1px);
}

.btn-primary {
  background: #c57632;
  box-shadow: 0 8px 20px rgba(197, 118, 50, 0.25);
  color: #fff;
}

.btn-primary:hover {
  background: #d68744;
}

.btn-ghost {
  background: rgba(15, 51, 65, 0.08);
  color: #0f3341;
}

.btn-ghost:hover {
  background: rgba(15, 51, 65, 0.14);
}

.pitch {
  align-items: start;
  background: linear-gradient(90deg, #c57632, #d28a4d);
  border-radius: 24px;
  color: #fff;
  display: grid;
  gap: 1rem;
  grid-template-columns: 1fr;
  padding: 1.4rem 1.5rem;
}

.pitch-title {
  font-size: clamp(1.3rem, 3vw, 2rem);
  font-weight: 800;
  line-height: 1.2;
}

.pitch-text {
  font-size: clamp(0.95rem, 2vw, 1.08rem);
  line-height: 1.6;
}

.benefits {
  display: grid;
  gap: 1.3rem;
}

.benefits-head {
  text-align: center;
}

h2 {
  color: #0f3341;
  font-size: clamp(1.4rem, 3vw, 2.2rem);
  margin-bottom: 0.35rem;
}

.benefits-head p {
  color: #54636e;
  margin: 0 auto;
  max-width: 65ch;
}

.benefit-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.benefit-card {
  background: #fff;
  border: 1px solid #f0e4d8;
  border-radius: 20px;
  box-shadow: 0 8px 22px rgba(15, 51, 65, 0.06);
  display: grid;
  gap: 0.6rem;
  padding: 1rem;
}

.benefit-icon {
  height: 68px;
  width: 68px;
}

h3 {
  color: #0f3341;
  font-size: 1.08rem;
  font-weight: 800;
}

.benefit-card p {
  color: #4d5a63;
  font-size: 0.95rem;
  line-height: 1.5;
}

@media (min-width: 900px) {
  .hero {
    grid-template-columns: minmax(300px, 1fr) 1.1fr;
    padding: 2.2rem;
  }

  .pitch {
    align-items: center;
    grid-template-columns: 1fr 1fr;
    padding: 1.6rem 2rem;
  }
}
</style>