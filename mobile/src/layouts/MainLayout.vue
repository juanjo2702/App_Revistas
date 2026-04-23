<template>
  <q-layout
    view="hHh lpr fFf"
    class="app-shell"
  >
    <q-page-container>
      <router-view />
    </q-page-container>

    <q-footer
      v-if="showTabs"
      bordered
      class="bg-white bottom-safe-area"
    >
      <q-tabs
        dense
        indicator-color="transparent"
        active-color="primary"
        class="text-grey-7"
      >
        <q-route-tab
          icon="home"
          label="Inicio"
          :to="{ name: 'home' }"
        />
        <q-route-tab
          icon="library_books"
          label="Revistas"
          :to="{ name: 'journals' }"
        />
        <q-route-tab
          icon="tune"
          label="Preferencias"
          :to="{ name: 'preferences' }"
        />
        <q-route-tab
          icon="download_done"
          label="Descargas"
          :to="{ name: 'downloads' }"
        />
      </q-tabs>
    </q-footer>
  </q-layout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const showTabs = computed(() => route.meta.hideTabs !== true);
</script>
