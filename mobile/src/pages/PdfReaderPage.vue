<template>
  <q-page class="page-frame reader-surface">
    <div class="column q-gutter-md">
      <q-card class="glass-card">
        <q-card-section class="row items-center q-col-gutter-sm">
          <div class="col-auto">
            <q-btn
              flat
              round
              icon="arrow_back"
              color="primary"
              @click="router.back()"
            />
          </div>
          <div class="col">
            <div class="text-overline text-secondary">Lector in-app</div>
            <div class="text-subtitle1 text-brand-title ellipsis">
              {{ readerTitle }}
            </div>
          </div>
        </q-card-section>

        <q-separator inset />

        <q-card-section class="row q-col-gutter-sm items-center">
          <div class="col-12 col-md">
            <div class="muted-copy">
              {{ readerSubtitle }}
            </div>
          </div>
          <div class="col-12 col-md-auto row q-gutter-xs reader-actions">
            <q-btn
              flat
              round
              color="primary"
              icon="remove"
              @click="adjustScale(-0.1)"
            />
            <q-chip
              color="white"
              text-color="primary"
              class="pill-chip"
            >
              {{ Math.round(renderScale * 100) }}%
            </q-chip>
            <q-btn
              flat
              round
              color="primary"
              icon="add"
              @click="adjustScale(0.1)"
            />
            <q-btn
              unelevated
              rounded
              color="secondary"
              icon="download"
              :loading="downloading"
              @click="downloadPdf"
            />
            <q-btn
              v-if="cachedDownload"
              flat
              rounded
              color="accent"
              icon="folder_open"
              label="Abrir copia"
              @click="openCachedDownload"
            />
          </div>
        </q-card-section>
      </q-card>

      <div
        v-if="loading"
        class="empty-state glass-card"
      >
        <q-spinner-gears
          color="primary"
          size="46px"
        />
        <div class="text-subtitle1 text-brand-title">Preparando visor PDF</div>
        <div class="muted-copy">Estamos cargando el documento y renderizando sus páginas.</div>
      </div>

      <div
        v-else-if="errorMessage"
        class="empty-state glass-card"
      >
        <q-icon
          name="warning"
          size="48px"
          color="negative"
        />
        <div class="text-subtitle1 text-brand-title">No se pudo abrir el PDF</div>
        <div class="muted-copy">{{ errorMessage }}</div>
      </div>

      <div
        v-else
        class="pdf-scroll"
        @touchstart.passive="onTouchStart"
        @touchmove.prevent="onTouchMove"
        @touchend="onTouchEnd"
      >
        <div
          class="pdf-stage"
          :style="{ transform: `scale(${gestureScale})` }"
        >
          <div
            v-for="pageNumber in pageNumbers"
            :key="pageNumber"
            class="pdf-page-shell"
          >
            <canvas :ref="(element) => setCanvasRef(element, pageNumber)" />
          </div>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { useQuasar } from 'quasar';
import { useRoute, useRouter } from 'vue-router';
import * as pdfjsLib from 'pdfjs-dist';
import pdfjsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import {
  ensureArticleDownloaded,
  ensureIssueDownloaded,
  findDownload,
  openDownloadedFile,
  type DownloadDocumentType,
  type DownloadRecord,
} from 'src/services/download-manager';
import { buildIssuePdfUrl, buildPdfUrl, getArticle } from 'src/services/ojs-api';
import type { ArticleDetail, PdfAsset } from 'src/types/ojs';

interface Props {
  documentType: DownloadDocumentType;
  documentId: string;
}

interface ReaderDocument {
  documentType: DownloadDocumentType;
  id: string;
  title: string;
  subtitle: string;
  pdf: PdfAsset;
}

const props = defineProps<Props>();

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker;

const $q = useQuasar();
const route = useRoute();
const router = useRouter();

const readerDocument = ref<ReaderDocument | null>(null);
const articleDetail = ref<ArticleDetail | null>(null);
const cachedDownload = ref<DownloadRecord | null>(null);
const loading = ref(true);
const downloading = ref(false);
const errorMessage = ref('');
const renderScale = ref(1.2);
const gestureScale = ref(1);

const pdfDocument = shallowRef<pdfjsLib.PDFDocumentProxy | null>(null);
const canvasRegistry = new Map<number, HTMLCanvasElement>();
const pageNumbers = ref<number[]>([]);

const pinchState = {
  startDistance: 0,
  baseScale: 1,
};

const readerTitle = computed(() => readerDocument.value?.title || 'Abriendo documento...');
const readerSubtitle = computed(() => readerDocument.value?.subtitle || 'Documento PDF');

function clampScale(value: number) {
  return Math.min(2.8, Math.max(0.8, value));
}

function setCanvasRef(element: unknown, pageNumber: number) {
  if (element instanceof HTMLCanvasElement) {
    canvasRegistry.set(pageNumber, element);
    return;
  }

  canvasRegistry.delete(pageNumber);
}

function touchDistance(touches: TouchList) {
  const [first, second] = [touches[0], touches[1]];

  if (!first || !second) {
    return 0;
  }

  return Math.hypot(second.clientX - first.clientX, second.clientY - first.clientY);
}

function onTouchStart(event: TouchEvent) {
  if (event.touches.length !== 2) {
    return;
  }

  pinchState.startDistance = touchDistance(event.touches);
  pinchState.baseScale = renderScale.value;
  gestureScale.value = 1;
}

function onTouchMove(event: TouchEvent) {
  if (event.touches.length !== 2 || pinchState.startDistance === 0) {
    return;
  }

  const ratio = touchDistance(event.touches) / pinchState.startDistance;
  gestureScale.value = Math.min(1.5, Math.max(0.85, ratio));
}

function onTouchEnd() {
  if (pinchState.startDistance === 0) {
    return;
  }

  renderScale.value = clampScale(pinchState.baseScale * gestureScale.value);
  gestureScale.value = 1;
  pinchState.startDistance = 0;
}

function adjustScale(delta: number) {
  renderScale.value = clampScale(renderScale.value + delta);
}

function buildCurrentPdfUrl(disposition: 'inline' | 'attachment') {
  return props.documentType === 'issue'
    ? buildIssuePdfUrl(props.documentId, disposition)
    : buildPdfUrl(props.documentId, disposition);
}

function buildIssueReaderDocument(): ReaderDocument {
  const title = typeof route.query.title === 'string' && route.query.title.trim() !== ''
    ? route.query.title
    : 'Número completo';
  const filename = typeof route.query.filename === 'string' && route.query.filename.trim() !== ''
    ? route.query.filename
    : `${props.documentId}.pdf`;

  return {
    documentType: 'issue',
    id: props.documentId,
    title,
    subtitle: 'Número completo',
    pdf: {
      url: buildIssuePdfUrl(props.documentId, 'inline'),
      mimeType: 'application/pdf',
      filename,
      downloadable: true,
    },
  };
}

async function loadDocument(): Promise<ReaderDocument> {
  if (props.documentType === 'issue') {
    return buildIssueReaderDocument();
  }

  const article = await getArticle(props.documentId);

  if (!article.pdf) {
    throw new Error('El artículo no expone un PDF descargable en este momento.');
  }

  articleDetail.value = article;

  return {
    documentType: 'article',
    id: article.id,
    title: article.title,
    subtitle: article.authorsString || article.authors.join(', ') || 'Autoría no disponible',
    pdf: article.pdf,
  };
}

async function renderPdfPages() {
  if (!pdfDocument.value) {
    return;
  }

  await nextTick();

  const devicePixelRatio = window.devicePixelRatio || 1;

  for (const pageNumber of pageNumbers.value) {
    const page = await pdfDocument.value.getPage(pageNumber);
    const viewport = page.getViewport({ scale: renderScale.value });
    const canvas = canvasRegistry.get(pageNumber);

    if (!canvas) {
      continue;
    }

    const context = canvas.getContext('2d');

    if (!context) {
      continue;
    }

    canvas.width = Math.floor(viewport.width * devicePixelRatio);
    canvas.height = Math.floor(viewport.height * devicePixelRatio);
    canvas.style.width = `${viewport.width}px`;
    canvas.style.height = `${viewport.height}px`;

    context.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
    context.clearRect(0, 0, canvas.width, canvas.height);

    await page.render({
      canvas,
      canvasContext: context,
      viewport,
    }).promise;
  }
}

async function loadReader() {
  loading.value = true;
  errorMessage.value = '';

  try {
    readerDocument.value = await loadDocument();
    cachedDownload.value = await findDownload(props.documentType, props.documentId);

    const loadingTask = pdfjsLib.getDocument({
      url: buildCurrentPdfUrl('inline'),
    });

    pdfDocument.value = await loadingTask.promise;
    pageNumbers.value = Array.from({ length: pdfDocument.value.numPages }, (_, index) => index + 1);
    await renderPdfPages();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Error inesperado al abrir el visor.';
  } finally {
    loading.value = false;
  }
}

async function downloadPdf() {
  if (!readerDocument.value) {
    return;
  }

  downloading.value = true;

  try {
    if (props.documentType === 'issue') {
      cachedDownload.value = await ensureIssueDownloaded({
        id: readerDocument.value.id,
        title: readerDocument.value.title,
        pdf: readerDocument.value.pdf,
      });
    } else {
      if (!articleDetail.value) {
        throw new Error('El artículo todavía no está listo para descarga.');
      }

      cachedDownload.value = await ensureArticleDownloaded(articleDetail.value);
    }

    $q.notify({
      type: 'positive',
      message: 'PDF guardado correctamente.',
    });
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'No se pudo guardar el PDF.',
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
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'No se pudo abrir la copia guardada.',
    });
  }
}

watch(renderScale, async () => {
  if (pdfDocument.value) {
    await renderPdfPages();
  }
});

onMounted(loadReader);

onBeforeUnmount(async () => {
  await pdfDocument.value?.destroy();
});
</script>

<style scoped>
@media (max-width: 599px) {
  .reader-actions {
    width: 100%;
    justify-content: space-between;
  }
}
</style>
