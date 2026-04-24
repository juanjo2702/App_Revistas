<template>
  <q-page class="page-frame">
    <div class="column q-gutter-md">
      <q-card class="glass-card hero-gradient home-hero">
        <q-card-section class="q-pa-lg q-pa-sm-xl">
          <div class="hero-top">
            <div class="hero-brand">
              <img
                :src="logoUrl"
                alt="Logo Revistas UNITEPC"
                class="hero-logo"
              >
            </div>

            <div class="hero-copy">
              <div class="text-overline text-white text-weight-bold">
                Revistas UNITEPC
              </div>
              <div class="text-caption text-white">
                Universidad Técnica Privada Cosmos
              </div>
            </div>
          </div>

          <h1 class="hero-title text-brand-title">
            Revistas científicas de la Universidad en una sola app
          </h1>

          <p class="hero-description">
            Explora revistas, abre artículos y números completos en PDF sin salir de la app y
            conserva tus lecturas descargadas para volver a ellas cuando quieras.
          </p>

          <div class="hero-actions">
            <q-btn
              unelevated
              rounded
              color="white"
              text-color="primary"
              label="Explorar revistas"
              icon-right="arrow_forward"
              class="hero-primary-btn"
              @click="router.push({ name: 'journals' })"
            />

            <q-btn
              flat
              rounded
              color="white"
              label="Ver descargas"
              icon="download_done"
              class="hero-secondary-btn"
              @click="router.push({ name: 'downloads' })"
            />
          </div>
        </q-card-section>
      </q-card>

      <div class="row q-col-gutter-md">
        <div class="col-12 col-sm-6">
          <q-card class="glass-card">
            <q-card-section>
              <div class="text-overline text-secondary">Colecciones activas</div>
              <div class="text-h4 text-brand-title q-mt-xs">{{ sources.length }}</div>
              <div class="muted-copy">Portales editoriales disponibles para consulta.</div>
            </q-card-section>
          </q-card>
        </div>

        <div class="col-12 col-sm-6">
          <q-card class="glass-card">
            <q-card-section>
              <div class="text-overline text-secondary">Descargas locales</div>
              <div class="text-h4 text-brand-title q-mt-xs">{{ downloadsCount }}</div>
              <div class="muted-copy">Documentos guardados para leer incluso sin conexión.</div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <section>
        <div class="section-title">
          <h2>Colecciones disponibles</h2>
          <q-btn
            flat
            round
            color="primary"
            icon="refresh"
            :loading="loading"
            @click="loadSnapshot"
          />
        </div>

        <div
          v-if="loading"
          class="empty-state glass-card"
        >
          <q-spinner-puff
            color="primary"
            size="42px"
          />
          <div class="muted-copy">Cargando colecciones...</div>
        </div>

        <div
          v-else
          class="column q-gutter-sm"
        >
          <q-card
            v-for="source in sources"
            :key="source.slug"
            class="glass-card"
          >
            <q-card-section class="row items-start q-col-gutter-md source-card">
              <div class="col-12 col-sm">
                <div class="text-overline text-secondary">Colección universitaria</div>
                <div class="text-h6 text-brand-title q-mt-xs">{{ source.name }}</div>
                <div class="muted-copy ellipsis-2-lines">
                  Disponible para lectura móvil desde la app.
                </div>
              </div>

              <div class="col-12 col-sm-auto">
                <q-btn
                  unelevated
                  rounded
                  color="primary"
                  class="source-action-btn"
                  label="Ver revistas"
                  @click="router.push({ name: 'journals', query: { source: source.slug } })"
                />
              </div>
            </q-card-section>
          </q-card>
        </div>
      </section>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useQuasar } from 'quasar';
import { useRouter } from 'vue-router';
import { reconcileDownloads } from 'src/services/download-manager';
import { getSources } from 'src/services/ojs-api';
import type { SourceSummary } from 'src/types/ojs';
import logoUrl from 'src/assets/logoU.png';

const $q = useQuasar();
const router = useRouter();

const sources = ref<SourceSummary[]>([]);
const downloadsCount = ref(0);
const loading = ref(false);

async function loadSnapshot() {
  loading.value = true;

  try {
    const [nextSources, nextDownloads] = await Promise.all([
      getSources(),
      reconcileDownloads(),
    ]);

    sources.value = nextSources;
    downloadsCount.value = nextDownloads.length;
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'No pudimos cargar la pantalla inicial.',
    });
  } finally {
    loading.value = false;
  }
}

onMounted(loadSnapshot);
</script>

<style scoped>
.home-hero {
  border-radius: 28px;
}

.hero-top {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 18px;
}

.hero-brand {
  flex: 0 0 auto;
}

.hero-logo {
  width: 76px;
  height: 76px;
  border-radius: 22px;
  background: white;
  padding: 6px;
  object-fit: contain;
}

.hero-copy {
  min-width: 0;
}

.hero-title {
  margin: 0 0 14px;
  max-width: 10ch;
  font-size: clamp(2.45rem, 11vw, 4.6rem);
  line-height: 0.94;
  letter-spacing: -0.055em;
  text-wrap: balance;
}

.hero-description {
  margin: 0 0 20px;
  max-width: 32rem;
  font-size: clamp(1rem, 2.9vw, 1.15rem);
  line-height: 1.55;
  color: rgba(255, 255, 255, 0.92);
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.hero-primary-btn,
.hero-secondary-btn,
.source-action-btn {
  min-height: 46px;
}

@media (max-width: 599px) {
  .hero-top {
    align-items: flex-start;
    gap: 12px;
  }

  .hero-logo {
    width: 64px;
    height: 64px;
    border-radius: 18px;
  }

  .hero-actions {
    display: grid;
    grid-template-columns: 1fr;
  }

  .hero-primary-btn,
  .hero-secondary-btn,
  .source-action-btn {
    width: 100%;
  }

  .source-card {
    row-gap: 14px;
  }
}
</style>
