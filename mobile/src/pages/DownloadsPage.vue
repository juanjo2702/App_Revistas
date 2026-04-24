<template>
  <q-page class="page-frame">
    <div class="column q-gutter-md">
      <q-card class="glass-card">
        <q-card-section class="row items-center justify-between q-col-gutter-md">
          <div class="col-12 col-sm">
            <div class="text-overline text-secondary">Lectura sin conexión</div>
            <div class="text-h5 text-brand-title q-mt-xs">Descargas guardadas</div>
            <div class="muted-copy q-mt-sm">
              Tus documentos descargados se revisan antes de mostrarse para mantener la biblioteca
              limpia y lista para abrir.
            </div>
          </div>
          <div class="col-12 col-sm-auto">
            <q-btn
              flat
              round
              color="primary"
              icon="refresh"
              :loading="loading"
              @click="refreshDownloads"
            />
          </div>
        </q-card-section>
      </q-card>

      <div
        v-if="loading"
        class="empty-state glass-card"
      >
        <q-spinner-tail
          color="primary"
          size="42px"
        />
        <div class="muted-copy">Verificando documentos guardados...</div>
      </div>

      <div
        v-else-if="downloads.length === 0"
        class="empty-state glass-card"
      >
        <q-icon
          name="download_done"
          size="52px"
          color="secondary"
        />
        <div class="text-subtitle1 text-brand-title">Aún no tienes descargas</div>
        <div class="muted-copy">
          Cuando guardes un artículo o un número completo aparecerá aquí para abrirlo incluso sin
          conexión.
        </div>
      </div>

      <div
        v-else
        class="column q-gutter-sm"
      >
        <q-card
          v-for="download in downloads"
          :key="`${download.documentType}:${download.documentId}`"
          class="glass-card"
        >
          <q-card-section class="row items-start q-col-gutter-md">
            <div class="col-12 col-sm">
              <div class="text-h6 text-brand-title">{{ download.title }}</div>
              <div class="muted-copy q-mt-xs">
                {{ download.documentType === 'issue' ? 'Número completo' : 'Artículo' }}
              </div>
              <div class="muted-copy q-mt-xs">{{ download.filename }}</div>
              <div class="muted-copy q-mt-sm">
                Guardado {{ formatDate(download.downloadedAt) }}
              </div>
            </div>

            <div class="col-12 col-sm-auto column q-gutter-sm">
              <q-btn
                unelevated
                rounded
                color="primary"
                icon="open_in_new"
                label="Abrir"
                class="full-width-mobile"
                @click="openDownload(download)"
              />
              <q-btn
                flat
                rounded
                color="negative"
                icon="delete"
                label="Eliminar"
                class="full-width-mobile"
                @click="removeItem(download.documentType, download.documentId)"
              />
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useQuasar } from 'quasar';
import {
  deleteDownload,
  openDownloadedFile,
  reconcileDownloads,
  type DownloadRecord,
} from 'src/services/download-manager';

const $q = useQuasar();
const downloads = ref<DownloadRecord[]>([]);
const loading = ref(false);

async function refreshDownloads() {
  loading.value = true;

  try {
    downloads.value = await reconcileDownloads();
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'No pudimos cargar tus descargas.',
    });
  } finally {
    loading.value = false;
  }
}

async function openDownload(download: DownloadRecord) {
  try {
    await openDownloadedFile(download);
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'No pudimos abrir el documento guardado.',
    });
  }
}

async function removeItem(documentType: DownloadRecord['documentType'], documentId: string) {
  try {
    downloads.value = await deleteDownload(documentType, documentId);
    $q.notify({
      type: 'positive',
      message: 'La descarga se eliminó del dispositivo.',
    });
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'No pudimos eliminar el archivo.',
    });
  }
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('es-BO', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

onMounted(refreshDownloads);
</script>

<style scoped>
@media (max-width: 599px) {
  .full-width-mobile {
    width: 100%;
  }
}
</style>
