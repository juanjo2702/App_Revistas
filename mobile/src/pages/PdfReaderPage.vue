<template>
  <q-page class="page-frame reader-surface native-reader-page">
    <div class="native-reader-shell">
      <q-card class="glass-card native-reader-card">
        <q-card-section class="native-reader-card__header">
          <q-btn
            flat
            round
            icon="arrow_back"
            color="primary"
            @click="router.back()"
          />

          <div class="native-reader-card__copy">
            <div class="text-overline text-secondary">{{ documentKicker }}</div>
            <div class="text-h5 text-brand-title native-reader-title">
              {{ readerTitle }}
            </div>
            <div class="muted-copy native-reader-subtitle">
              {{ readerSubtitle }}
            </div>
          </div>
        </q-card-section>

        <q-separator inset />

        <q-card-section class="column q-gutter-md">
          <div
            v-if="loading"
            class="native-reader-status"
          >
            <q-spinner-gears
              color="primary"
              size="48px"
            />
            <div class="text-subtitle1 text-brand-title">Abriendo PDF</div>
            <div class="muted-copy">
              Estamos preparando el archivo para abrirlo con el visor del dispositivo.
            </div>
          </div>

          <div
            v-else-if="errorMessage"
            class="native-reader-status"
          >
            <q-icon
              name="warning"
              size="52px"
              color="negative"
            />
            <div class="text-subtitle1 text-brand-title">No pudimos abrir este documento</div>
            <div class="muted-copy">{{ errorMessage }}</div>
          </div>

          <div
            v-else
            class="native-reader-status"
          >
            <q-icon
              name="picture_as_pdf"
              size="56px"
              color="primary"
            />
            <div class="text-subtitle1 text-brand-title">PDF listo para leer</div>
            <div class="muted-copy">
              Este documento se abre con el visor del teléfono para ofrecer una lectura más fluida,
              rápida y estable.
            </div>
          </div>

          <div class="native-reader-actions">
            <q-btn
              unelevated
              rounded
              color="primary"
              icon="open_in_new"
              :loading="opening"
              label="Abrir PDF"
              @click="openDocument"
            />

            <q-btn
              flat
              rounded
              color="secondary"
              icon="download"
              :loading="downloading"
              label="Guardar copia"
              @click="downloadCopy"
            />

            <q-btn
              v-if="cachedDownload"
              flat
              rounded
              color="accent"
              icon="folder_open"
              label="Abrir copia guardada"
              @click="openCachedDownload"
            />
          </div>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useQuasar } from 'quasar';
import { useRoute, useRouter } from 'vue-router';
import {
  ensureArticleDownloaded,
  ensureIssueDownloaded,
  findDownload,
  openDownloadedFile,
  type DownloadDocumentType,
  type DownloadRecord,
} from 'src/services/download-manager';
import { getArticle } from 'src/services/ojs-api';
import type { ArticleDetail, IssueSummary } from 'src/types/ojs';

interface Props {
  documentType: DownloadDocumentType;
  documentId: string;
}

const props = defineProps<Props>();

const $q = useQuasar();
const route = useRoute();
const router = useRouter();

const articleDetail = ref<ArticleDetail | null>(null);
const issueDetail = ref<Pick<IssueSummary, 'id' | 'title' | 'pdf'> | null>(null);
const cachedDownload = ref<DownloadRecord | null>(null);
const loading = ref(true);
const opening = ref(false);
const downloading = ref(false);
const errorMessage = ref('');

const readerTitle = computed(() => {
  if (props.documentType === 'issue') {
    return issueDetail.value?.title || route.query.title || 'Número completo';
  }

  return articleDetail.value?.title || 'Artículo';
});

const readerSubtitle = computed(() => {
  if (props.documentType === 'issue') {
    return 'Se abrirá con el visor del dispositivo.';
  }

  return articleDetail.value?.authorsString || articleDetail.value?.authors.join(', ') || 'Se abrirá con el visor del dispositivo.';
});

const documentKicker = computed(() => (
  props.documentType === 'issue' ? 'Número completo' : 'Artículo completo'
));

async function resolveDocument() {
  cachedDownload.value = await findDownload(props.documentType, props.documentId);

  if (props.documentType === 'issue') {
    const title = typeof route.query.title === 'string' && route.query.title.trim() !== ''
      ? route.query.title
      : 'Número completo';
    const filename = typeof route.query.filename === 'string' && route.query.filename.trim() !== ''
      ? route.query.filename
      : `${props.documentId}.pdf`;

    issueDetail.value = {
      id: props.documentId,
      title,
      pdf: {
        url: '',
        mimeType: 'application/pdf',
        filename,
        downloadable: true,
      },
    };

    return;
  }

  const article = await getArticle(props.documentId);

  if (!article.pdf) {
    throw new Error('Este artículo no tiene un PDF disponible en este momento.');
  }

  articleDetail.value = article;
}

async function ensureDocumentReady() {
  if (props.documentType === 'issue') {
    if (!issueDetail.value) {
      throw new Error('No pudimos preparar este número.');
    }

    cachedDownload.value = await ensureIssueDownloaded(issueDetail.value);
    return cachedDownload.value;
  }

  if (!articleDetail.value) {
    throw new Error('No pudimos preparar este artículo.');
  }

  cachedDownload.value = await ensureArticleDownloaded(articleDetail.value);
  return cachedDownload.value;
}

async function openDocument() {
  if (loading.value) {
    return;
  }

  opening.value = true;

  try {
    const readyDocument = await ensureDocumentReady();
    await openDownloadedFile(readyDocument);
  } catch (error) {
    const message = error instanceof Error ? error.message : 'No pudimos abrir el PDF.';
    errorMessage.value = message;
    $q.notify({
      type: 'negative',
      message,
    });
  } finally {
    opening.value = false;
  }
}

async function downloadCopy() {
  if (loading.value) {
    return;
  }

  downloading.value = true;

  try {
    await ensureDocumentReady();
    $q.notify({
      type: 'positive',
      message: 'La copia se guardó correctamente.',
    });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'No pudimos guardar la copia.';
    errorMessage.value = message;
    $q.notify({
      type: 'negative',
      message,
    });
  } finally {
    downloading.value = false;
  }
}

async function openCachedDownload() {
  if (!cachedDownload.value) {
    return;
  }

  try {
    await openDownloadedFile(cachedDownload.value);
  } catch (error) {
    const message = error instanceof Error ? error.message : 'No pudimos abrir la copia guardada.';
    $q.notify({
      type: 'negative',
      message,
    });
  }
}

async function bootstrap() {
  loading.value = true;
  errorMessage.value = '';

  try {
    await resolveDocument();
    loading.value = false;
    await openDocument();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'No pudimos preparar este documento.';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  void bootstrap();
});
</script>

<style scoped>
.native-reader-page {
  display: flex;
  align-items: center;
}

.native-reader-shell {
  width: 100%;
}

.native-reader-card {
  max-width: 760px;
  margin: 0 auto;
}

.native-reader-card__header {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 12px;
  align-items: start;
}

.native-reader-card__copy {
  min-width: 0;
}

.native-reader-title {
  margin-top: 4px;
  line-height: 1.14;
  word-break: break-word;
}

.native-reader-subtitle {
  margin-top: 8px;
}

.native-reader-status {
  display: grid;
  place-items: center;
  text-align: center;
  gap: 12px;
  padding: 12px 4px;
}

.native-reader-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
}

@media (max-width: 599px) {
  .native-reader-actions {
    display: grid;
    grid-template-columns: 1fr;
  }

  .native-reader-actions :deep(.q-btn) {
    width: 100%;
  }
}
</style>
