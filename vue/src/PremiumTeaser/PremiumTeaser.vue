<!--
  Matomo - free/libre analytics platform

  @link    https://matomo.org
  @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class="card ee-premium">
    <div class="card-content">
      <h2 class="card-title ee-premium-title">
        <span class="ee-premium-title-text">{{ name }}</span>
        <span class="ee-premium-badge">Premium</span>
      </h2>
      <p class="ee-premium-description">{{ description }}</p>
      <div class="ee-premium-preview">
        <img class="ee-premium-image" :src="image" alt="" loading="lazy">
        <div class="ee-premium-overlay">
          <p class="ee-premium-link">
            <span class="icon-locked"></span>
            <a :href="premiumUrl" target="_blank" rel="noreferrer noopener">
              {{ translate('EcommerceEnhanced_PremiumLinkText') }}
            </a>
            {{ translate('EcommerceEnhanced_PremiumText', name) }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

export default defineComponent({
  props: {
    name: {
      type: String,
      required: true,
    },
    description: {
      type: String,
      required: true,
    },
    image: {
      type: String,
      required: true,
    },
    premiumUrl: {
      type: String,
      required: true,
    },
  },
});
</script>
