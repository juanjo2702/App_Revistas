<template>
  <router-view />
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { initializeDeviceContext } from 'src/services/device-context';
import { setupPushListeners } from 'src/services/push-manager';

onMounted(() => {
  void initializeDeviceContext();
  void setupPushListeners();
});
</script>
