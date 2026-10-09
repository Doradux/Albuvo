<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ArrowUpRight, ArrowRight, ShieldCheck, UsersRound, ImagePlus, QrCode, Heart, Check, Sparkles, LockKeyhole, Images } from '@lucide/vue'
</script>
<template>
  <div class="home">
    <section class="hero">
      <div class="container hero-inner">
        <div class="hero-copy">
          <div class="pill"><Sparkles :size="14"/> Los mejores recuerdos se hacen juntos</div>
          <h1>Los momentos pasan.<br><em>Las historias</em> se quedan.</h1>
          <p class="hero-subtitle">Crea un álbum, compártelo con los tuyos y reúne cada foto de esos momentos que merecen quedarse. Todo en un solo lugar.</p>
          <div class="hero-buttons">
            <RouterLink to="/signup" class="btn btn-dark btn-hero">Crear mi primer álbum <ArrowUpRight :size="19"/></RouterLink>
            <a href="#funciona" class="btn btn-light">Descubre cómo funciona <ArrowRight :size="17"/></a>
          </div>
          <div class="hero-note"><span class="avatars"><b>✦</b><b>☺</b><b>♥</b></span><span>Gratis para empezar. Y para compartir, siempre.</span></div>
        </div>
        <div class="hero-art" aria-label="Ilustración de un álbum colaborativo">
          <div class="hero-blob"></div>
          <div class="memory memory-top"><span class="memory-visual memory-visual-sunset">☀</span><span class="memory-caption">Una tarde inolvidable <Heart :size="14" fill="#e6a9a0" color="#e6a9a0"/></span></div>
          <div class="memory memory-left"><span class="memory-visual memory-visual-mountains">✺</span><span class="memory-caption">Nuestro viaje ✳</span></div>
          <div class="memory memory-right"><span class="memory-visual memory-visual-flower">✿</span><span class="memory-caption">Momentos bonitos ♡</span></div>
          <div class="float-sticker"><span>♡</span><strong>más recuerdos,<br>más juntos</strong></div>
          <div class="float-bubble"><UsersRound :size="19"/><span>Las fotos de todos,<br><strong>en un mismo álbum</strong></span></div>
        </div>
      </div>
    </section>
    <section class="trust-band"><div class="container trust-band-inner"><span><LockKeyhole :size="18"/> Álbumes privados</span><span><UsersRound :size="18"/> Colaboración sin complicaciones</span><span><ShieldCheck :size="18"/> Tú decides quién participa</span></div></section>
    <section id="funciona" class="spaced how-section"><div class="container">
      <div class="section-heading"><span class="eyebrow">Así de fácil</span><h2>Un álbum.<br>Mil perspectivas.</h2><p>No importa quién hizo la foto. Lo importante es poder disfrutarla juntos.</p></div>
      <div class="how-grid">
        <article class="step-card"><span class="step-number">01 /</span><div class="step-graphic graphic-one"><Images :size="68" :stroke-width="1.4"/></div><h3>Crea tu álbum</h3><p>Ponle nombre a tu historia: un viaje, un cumpleaños o simplemente un buen día.</p></article>
        <article class="step-card"><span class="step-number">02 /</span><div class="step-graphic graphic-two"><QrCode :size="74" :stroke-width="1.3"/></div><h3>Invita a los tuyos</h3><p>Comparte un enlace o código QR. Pueden aportar fotos sin necesidad de registrarse.</p></article>
        <article class="step-card"><span class="step-number">03 /</span><div class="step-graphic graphic-three"><ImagePlus :size="70" :stroke-width="1.3"/></div><h3>Disfrutadlo juntos</h3><p>Todas las perspectivas reunidas en una galería preciosa y bajo tu control.</p></article>
      </div>
    </div></section>
    <section id="privacidad" class="privacy-section spaced"><div class="container privacy-inner">
      <div class="privacy-art"><div class="privacy-card"><div class="privacy-card-top"><span>Recuerdos de verano</span><ShieldCheck :size="23"/></div><div class="privacy-squares"><i>☀</i><i>❀</i><i>✦</i><i>♧</i></div><div class="privacy-status"><span class="status-led"></span> Solo las personas que tú elijas</div></div><span class="privacy-floating"><LockKeyhole :size="20"/> Espacio privado</span></div>
      <div class="privacy-copy"><span class="eyebrow">Tus recuerdos son tuyos</span><h2>Compartir no debería significar perder tu privacidad.</h2><p>Tú decides quién puede ver, subir o descargar. Puedes revisar las fotos antes de publicarlas y revocar invitaciones cuando quieras.</p><div class="privacy-highlights"><span><Check :size="17"/> Privacidad por defecto</span><span><Check :size="17"/> Moderación a tu manera</span><span><Check :size="17"/> Sin feed social público</span></div><RouterLink to="/signup" class="btn btn-dark">Empieza con tu álbum <ArrowUpRight :size="18"/></RouterLink></div>
    </div></section>
    <section id="planes" class="spaced pricing-section"><div class="container"><div class="pricing-intro"><span class="eyebrow">Pensado para cada historia</span><h2>Compartir siempre<br>es el mejor plan.</h2><p>Empieza gratis y reúne las fotos de todos. Las opciones de más calidad y espacio se están preparando.</p></div><div class="pricing-grid"><div class="price-card free"><span class="pill">Disponible en desarrollo</span><h3>Free</h3><div class="price">0 € <small>/ álbum</small></div><p>Para esos momentos que simplemente hay que guardar.</p><ul><li><Check :size="17"/> Álbumes colaborativos</li><li><Check :size="17"/> Invitaciones y código QR</li><li><Check :size="17"/> Moderación de fotos</li><li><Check :size="17"/> Hasta 1 GB por álbum</li></ul><RouterLink to="/signup" class="btn btn-dark">Crear álbum gratis <ArrowUpRight :size="17"/></RouterLink></div><div class="price-card future"><span class="pill">Próximamente</span><h3>Plus y Pro</h3><div class="price">Más espacio.<br>Más calidad.</div><p>Estamos preparando opciones para quienes quieren conservar todavía más.</p><ul><li><Check :size="17"/> Más capacidad de almacenamiento</li><li><Check :size="17"/> Mayor resolución</li><li><Check :size="17"/> Opciones de vídeo</li><li><Check :size="17"/> Compra por álbum, sin pagar por invitado</li></ul><span class="price-warning">Precios, límites y disponibilidad sujetos a validación.</span></div></div></div></section>
    <section class="final-cta"><div class="container"><span class="eyebrow">¿Tienes fotos pendientes de compartir?</span><h2>Que no se pierda<br>ni un solo recuerdo.</h2><RouterLink to="/signup" class="btn btn-dark">Crear un álbum gratis <ArrowUpRight :size="19"/></RouterLink><div class="final-flower">✿</div></div></section>
  </div>
</template>
