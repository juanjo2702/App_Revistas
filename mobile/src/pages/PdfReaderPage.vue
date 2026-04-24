<template>
  <q-page class="page-frame reader-surface">
    <div class="column q-gutter-md">
      <q-card class="glass-card reader-header-card">
        <q-card-section class="row items-start q-col-gutter-sm">
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
            <div class="text-subtitle1 text-brand-title reader-title">
              {{ readerTitle }}
            </div>
            <div class="muted-copy reader-subtitle">
              {{ readerSubtitle }}
            </div>
          </div>
        </q-card-section>

        <q-separator inset />

        <q-card-section
          v-if="!loading && !errorMessage && totalPages > 0"
          class="reader-toolbar"
        >
          <div class="reader-toolbar__group">
            <q-btn
              flat
              round
              color="primary"
              icon="first_page"
              :disable="currentPage <= 1"
              @click="jumpToPage(1)"
            />
            <q-btn
              flat
              round
              color="primary"
              icon="chevron_left"
              :disable="currentPage <= 1"
              @click="jumpToPage(currentPage - 1)"
            />
            <q-chip
              color="white"
              text-color="primary"
              class="pill-chip reader-indicator"
            >
              Página {{ currentPage }} / {{ totalPages }}
            </q-chip>
            <q-btn
              flat
              round
              color="primary"
              icon="chevron_right"
              :disable="currentPage >= totalPages"
              @click="jumpToPage(currentPage + 1)"
            />
            <q-btn
              flat
              round
              color="primary"
              icon="last_page"
              :disable="currentPage >= totalPages"
              @click="jumpToPage(totalPages)"
            />
          </div>

          <div class="reader-toolbar__group">
            <q-btn
              flat
              round
              color="primary"
              icon="remove"
              @click="adjustZoom(-0.15)"
            />
            <q-chip
              color="white"
              text-color="primary"
              class="pill-chip"
            >
              {{ zoomPercentage }}%
            </q-chip>
            <q-btn
              flat
              round
              color="primary"
              icon="add"
              @click="adjustZoom(0.15)"
            />
            <q-btn
              flat
              rounded
              color="accent"
              icon="fit_screen"
              label="Ajustar"
              @click="resetZoom"
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
        <div class="muted-copy">Estamos cargando el documento y preparando sus páginas.</div>
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
        ref="scrollContainerRef"
        class="pdf-scroll"
        @scroll.passive="onScroll"
        @touchstart.passive="onTouchStart"
        @touchmove="onTouchMove"
        @touchend.passive="onTouchEnd"
      >
        <div
          class="pdf-stage"
          :style="{ transform: `scale(${gestureScale})` }"
        >
          <section
            v-for="page in pageStates"
            :key="page.number"
            :ref="(element) => setPageShellRef(element, page.number)"
            class="pdf-page-shell"
            :style="pageShellStyle(page)"
          >
            <header class="pdf-page-shell__header">
              <span>Página {{ page.number }}</span>
              <q-spinner
                v-if="page.status === 'rendering'"
                color="primary"
                size="18px"
              />
            </header>

            <div
              class="pdf-page-frame"
              :style="pageFrameStyle(page)"
            >
              <canvas
                :ref="(element) => setCanvasRef(element, page.number)"
                class="pdf-page-canvas"
              />

              <div
                v-if="page.status !== 'rendered'"
                class="pdf-page-placeholder"
              >
                <q-spinner
                  v-if="page.status === 'rendering'"
                  color="primary"
                  size="28px"
                />
                <q-icon
                  v-else-if="page.status === 'error'"
                  name="warning"
                  color="negative"
                  size="26px"
                />
                <div class="muted-copy">
                  {{
                    page.status === 'error'
                      ? 'No se pudo renderizar esta página.'
                      : 'Cargando página...'
                  }}
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, shallowRef, computed, watch } from 'vue';
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

interface ReaderPageState {
  number: number;
  baseWidth: number;
  baseHeight: number;
  status: 'idle' | 'rendering' | 'rendered' | 'error';
  renderedScale: number | null;
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
const zoomFactor = ref(1);
const gestureScale = ref(1);
const fitScale = ref(1);
const currentPage = ref(1);

const pdfDocument = shallowRef<pdfjsLib.PDFDocumentProxy | null>(null);
const scrollContainerRef = ref<HTMLDivElement | null>(null);
const pageStates = ref<ReaderPageState[]>([]);
const visiblePages = ref<number[]>([]);

const canvasRegistry = new Map<number, HTMLCanvasElement>();
const pageShellRegistry = new Map<number, HTMLElement>();
const renderTasks = new Map<number, { cancel: () => void }>();

const pinchState = {
  startDistance: 0,
  baseZoom: 1,
};

let visibilityObserver: IntersectionObserver | null = null;
let resizeObserver: ResizeObserver | null = null;
let renderFrame: number | null = null;

const readerTitle = computed(() => readerDocument.value?.title || 'Abriendo documento...');
const readerSubtitle = computed(() => readerDocument.value?.subtitle || 'Documento PDF');
const totalPages = computed(() => pageStates.value.length);
const targetScale = computed(() => Math.max(0.6, fitScale.value * zoomFactor.value));
const zoomPercentage = computed(() => Math.round(zoomFactor.value * 100));

function clampZoom(value: number) {
  return Math.min(2.6, Math.max(0.75, value));
}

function patchPageState(pageNumber: number, patch: Partial<ReaderPageState>) {
  pageStates.value = pageStates.value.map((page) => (
    page.number === pageNumber
      ? { ...page, ...patch }
      : page
  ));
}

function setCanvasRef(element: unknown, pageNumber: number) {
  if (element instanceof HTMLCanvasElement) {
    canvasRegistry.set(pageNumber, element);
    return;
  }

  canvasRegistry.delete(pageNumber);
}

function observePageShell(element: HTMLElement) {
  visibilityObserver?.observe(element);
}

function unobservePageShell(element: HTMLElement) {
  visibilityObserver?.unobserve(element);
}

function setPageShellRef(element: unknown, pageNumber: number) {
  const previous = pageShellRegistry.get(pageNumber);

  if (previous) {
    unobservePageShell(previous);
  }

  if (element instanceof HTMLElement) {
    element.dataset.pageNumber = String(pageNumber);
    pageShellRegistry.set(pageNumber, element);
    observePageShell(element);
    return;
  }

  pageShellRegistry.delete(pageNumber);
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
    pinchState.startDistance = 0;
    gestureScale.value = 1;
    return;
  }

  pinchState.startDistance = touchDistance(event.touches);
  pinchState.baseZoom = zoomFactor.value;
  gestureScale.value = 1;
}

function onTouchMove(event: TouchEvent) {
  if (event.touches.length !== 2 || pinchState.startDistance === 0) {
    return;
  }

  event.preventDefault();

  const ratio = touchDistance(event.touches) / pinchState.startDistance;
  gestureScale.value = Math.min(1.35, Math.max(0.85, ratio));
}

function onTouchEnd() {
  if (pinchState.startDistance === 0) {
    return;
  }

  zoomFactor.value = clampZoom(pinchState.baseZoom * gestureScale.value);
  gestureScale.value = 1;
  pinchState.startDistance = 0;
}

function adjustZoom(delta: number) {
  zoomFactor.value = clampZoom(zoomFactor.value + delta);
}

function resetZoom() {
  zoomFactor.value = 1;
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

function cancelRender(pageNumber: number) {
  const task = renderTasks.get(pageNumber);

  if (!task) {
    return;
  }

  task.cancel();
  renderTasks.delete(pageNumber);
}

function cancelAllRenders() {
  for (const pageNumber of renderTasks.keys()) {
    cancelRender(pageNumber);
  }
}

function measureViewport() {
  const containerWidth = scrollContainerRef.value?.clientWidth ?? window.innerWidth;
  const firstPage = pageStates.value[0];

  if (!firstPage || containerWidth <= 0) {
    return;
  }

  const availableWidth = Math.max(220, containerWidth - 24);
  fitScale.value = availableWidth / firstPage.baseWidth;
}

function pageFrameStyle(page: ReaderPageState) {
  return {
    width: `${page.baseWidth * targetScale.value}px`,
    minHeight: `${page.baseHeight * targetScale.value}px`,
  };
}

function pageShellStyle(page: ReaderPageState) {
  return {
    width: `${page.baseWidth * targetScale.value + 24}px`,
  };
}

function collectPagesToRender() {
  const requested = new Set<number>();
  const pool = visiblePages.value.length > 0 ? visiblePages.value : [currentPage.value];

  for (const pageNumber of pool) {
    requested.add(pageNumber);

    if (pageNumber > 1) {
      requested.add(pageNumber - 1);
    }

    if (pageNumber < totalPages.value) {
      requested.add(pageNumber + 1);
    }
  }

  return Array.from(requested).sort((left, right) => left - right);
}

async function renderPage(pageNumber: number) {
  const state = pageStates.value.find((page) => page.number === pageNumber);
  const document = pdfDocument.value;
  const canvas = canvasRegistry.get(pageNumber);

  if (!state || !document || !canvas) {
    return;
  }

  if (state.renderedScale === targetScale.value && state.status === 'rendered') {
    return;
  }

  cancelRender(pageNumber);
  patchPageState(pageNumber, { status: 'rendering' });

  try {
    const page = await document.getPage(pageNumber);
    const viewport = page.getViewport({ scale: targetScale.value });
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const context = canvas.getContext('2d', { alpha: false });

    if (!context) {
      throw new Error('No se pudo crear el contexto del canvas.');
    }

    canvas.width = Math.floor(viewport.width * dpr);
    canvas.height = Math.floor(viewport.height * dpr);
    canvas.style.width = `${viewport.width}px`;
    canvas.style.height = `${viewport.height}px`;

    context.setTransform(dpr, 0, 0, dpr, 0, 0);
    context.clearRect(0, 0, canvas.width, canvas.height);

    const task = page.render({
      canvas,
      canvasContext: context,
      viewport,
    });

    renderTasks.set(pageNumber, task);
    await task.promise;

    if (renderTasks.get(pageNumber) === task) {
      renderTasks.delete(pageNumber);
    }

    patchPageState(pageNumber, {
      status: 'rendered',
      renderedScale: targetScale.value,
      baseWidth: viewport.width / targetScale.value,
      baseHeight: viewport.height / targetScale.value,
    });
  } catch (error) {
    const message = error instanceof Error ? error.message.toLowerCase() : '';

    if (message.includes('cancelled') || message.includes('canceled')) {
      return;
    }

    patchPageState(pageNumber, { status: 'error' });
  }
}

function scheduleVisibleRender() {
  if (renderFrame !== null) {
    cancelAnimationFrame(renderFrame);
  }

  renderFrame = window.requestAnimationFrame(() => {
    renderFrame = null;

    void (async () => {
      for (const pageNumber of collectPagesToRender()) {
        await renderPage(pageNumber);
      }
    })();
  });
}

function syncCurrentPage() {
  const container = scrollContainerRef.value;

  if (!container || pageShellRegistry.size === 0) {
    return;
  }

  const containerRect = container.getBoundingClientRect();
  let closestPage = currentPage.value;
  let closestDistance = Number.POSITIVE_INFINITY;

  for (const [pageNumber, shell] of pageShellRegistry.entries()) {
    const rect = shell.getBoundingClientRect();

    if (rect.bottom < containerRect.top || rect.top > containerRect.bottom) {
      continue;
    }

    const distance = Math.abs(rect.top - containerRect.top - 12);

    if (distance < closestDistance) {
      closestDistance = distance;
      closestPage = pageNumber;
    }
  }

  currentPage.value = closestPage;
}

function updateVisiblePagesFromObserver(entries: IntersectionObserverEntry[]) {
  const nextVisible = new Set(visiblePages.value);

  for (const entry of entries) {
    const pageNumber = Number((entry.target as HTMLElement).dataset.pageNumber || '0');

    if (!pageNumber) {
      continue;
    }

    if (entry.isIntersecting) {
      nextVisible.add(pageNumber);
    } else {
      nextVisible.delete(pageNumber);
    }
  }

  visiblePages.value = Array.from(nextVisible).sort((left, right) => left - right);
  syncCurrentPage();
  scheduleVisibleRender();
}

function setupObservers() {
  visibilityObserver?.disconnect();
  resizeObserver?.disconnect();

  const container = scrollContainerRef.value;

  if (!container) {
    return;
  }

  visibilityObserver = new IntersectionObserver(updateVisiblePagesFromObserver, {
    root: container,
    rootMargin: '320px 0px 320px 0px',
    threshold: 0.01,
  });

  resizeObserver = new ResizeObserver(() => {
    measureViewport();
    scheduleVisibleRender();
  });

  resizeObserver.observe(container);

  for (const shell of pageShellRegistry.values()) {
    visibilityObserver.observe(shell);
  }
}

function onScroll() {
  syncCurrentPage();
  scheduleVisibleRender();
}

function jumpToPage(pageNumber: number) {
  const boundedPage = Math.min(Math.max(pageNumber, 1), totalPages.value);
  const target = pageShellRegistry.get(boundedPage);

  if (!target) {
    currentPage.value = boundedPage;
    return;
  }

  target.scrollIntoView({
    behavior: 'smooth',
    block: 'start',
  });
}

async function initializePages(document: pdfjsLib.PDFDocumentProxy) {
  const firstPage = await document.getPage(1);
  const firstViewport = firstPage.getViewport({ scale: 1 });

  pageStates.value = Array.from({ length: document.numPages }, (_, index) => ({
    number: index + 1,
    baseWidth: firstViewport.width,
    baseHeight: firstViewport.height,
    status: 'idle',
    renderedScale: null,
  }));

  currentPage.value = 1;
  visiblePages.value = [1];
}

async function loadReader() {
  loading.value = true;
  errorMessage.value = '';
  cancelAllRenders();

  try {
    readerDocument.value = await loadDocument();
    cachedDownload.value = await findDownload(props.documentType, props.documentId);

    const loadingTask = pdfjsLib.getDocument({
      url: buildCurrentPdfUrl('inline'),
    });

    pdfDocument.value = await loadingTask.promise;
    await initializePages(pdfDocument.value);
    await nextTick();
    measureViewport();
    setupObservers();
    scheduleVisibleRender();
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

watch(targetScale, () => {
  for (const page of pageStates.value) {
    patchPageState(page.number, {
      status: page.status === 'error' ? 'error' : 'idle',
      renderedScale: null,
    });
  }

  cancelAllRenders();
  scheduleVisibleRender();
});

onMounted(() => {
  void loadReader();
});

onBeforeUnmount(async () => {
  cancelAllRenders();
  visibilityObserver?.disconnect();
  resizeObserver?.disconnect();

  if (renderFrame !== null) {
    cancelAnimationFrame(renderFrame);
  }

  await pdfDocument.value?.destroy();
});
</script>

<style scoped>
.reader-header-card {
  position: sticky;
  top: 12px;
  z-index: 3;
}

.reader-title {
  line-height: 1.2;
  word-break: break-word;
}

.reader-subtitle {
  margin-top: 6px;
  font-size: 0.95rem;
}

.reader-toolbar {
  display: grid;
  gap: 12px;
}

.reader-toolbar__group {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.reader-indicator {
  min-width: 118px;
  justify-content: center;
}

.pdf-scroll {
  overflow: auto;
  min-height: calc(100vh - 205px);
  padding-bottom: 24px;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}

.pdf-stage {
  transform-origin: top center;
  transition: transform 120ms ease-out;
  padding: 2px 0 12px;
}

.pdf-page-shell {
  margin: 0 auto 18px;
  padding: 12px;
  background: rgba(255, 255, 255, 0.94);
  border-radius: 22px;
  box-shadow: 0 12px 26px rgba(20, 34, 48, 0.08);
}

.pdf-page-shell__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
  color: rgba(30, 36, 48, 0.72);
  font-size: 0.92rem;
}

.pdf-page-frame {
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  background: #f4f7fa;
}

.pdf-page-canvas {
  display: block;
  width: 100%;
  height: auto;
}

.pdf-page-placeholder {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  gap: 10px;
  text-align: center;
  background:
    linear-gradient(135deg, rgba(102, 51, 153, 0.05), rgba(0, 153, 153, 0.05)),
    #f4f7fa;
  padding: 24px;
}

@media (max-width: 599px) {
  .reader-header-card {
    top: 8px;
  }

  .pdf-scroll {
    min-height: calc(100vh - 220px);
  }

  .reader-toolbar__group {
    width: 100%;
  }

  .reader-toolbar__group :deep(.q-btn),
  .reader-toolbar__group :deep(.q-chip) {
    flex-shrink: 0;
  }
}
</style>
