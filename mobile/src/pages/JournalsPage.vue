<template>
  <q-page class="page-frame">
    <div class="column q-gutter-md">
      <q-card class="glass-card">
        <q-card-section class="catalog-hero">
          <div class="text-overline text-secondary">Catálogo universitario</div>
          <div class="text-h4 text-brand-title q-mt-xs">Revistas, números y artículos</div>
          <div class="muted-copy q-mt-sm">
            Explora las revistas científicas de la Universidad, abre números completos y encuentra
            artículos por título, autor, DOI o año.
          </div>
        </q-card-section>
      </q-card>

      <q-card class="glass-card search-card">
        <q-expansion-item
          v-model="searchExpanded"
          icon="manage_search"
          switch-toggle-side
          expand-separator
          header-class="search-card__header"
        >
          <template #header>
            <q-item-section>
              <q-item-label class="text-subtitle1 text-brand-title">Búsqueda avanzada</q-item-label>
              <q-item-label caption>
                Despliégala solo cuando quieras filtrar por texto, colección o año.
              </q-item-label>
            </q-item-section>

            <q-item-section side>
              <q-chip
                v-if="hasSearchContext"
                dense
                color="accent"
                text-color="white"
                class="pill-chip"
              >
                {{ searchResults.length }}
              </q-chip>
            </q-item-section>
          </template>

          <q-card-section class="column q-gutter-md q-pt-none">
            <q-input
              v-model="searchQuery"
              outlined
              clearable
              label="Título, autor o DOI"
              @keyup.enter="runSearch"
            >
              <template #append>
                <q-icon name="search" />
              </template>
            </q-input>

            <div class="row q-col-gutter-sm">
              <div class="col-12 col-sm-6">
                <q-select
                  v-model="searchSourceSlug"
                  :options="searchSourceOptions"
                  emit-value
                  map-options
                  outlined
                  label="Colección"
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-select
                  v-model="searchYear"
                  :options="yearOptions"
                  emit-value
                  map-options
                  outlined
                  clearable
                  label="Año"
                />
              </div>
            </div>

            <div class="search-actions">
              <q-btn
                unelevated
                rounded
                color="primary"
                icon="search"
                :loading="loadingSearch"
                label="Buscar"
                @click="runSearch"
              />
              <q-btn
                flat
                rounded
                color="secondary"
                icon="cleaning_services"
                label="Limpiar"
                @click="clearSearch"
              />
            </div>
          </q-card-section>
        </q-expansion-item>
      </q-card>

      <section v-if="hasSearchContext">
        <div class="section-title">
          <h2>Resultados</h2>
          <q-chip
            color="primary"
            text-color="white"
            class="pill-chip"
          >
            {{ searchResults.length }}
          </q-chip>
        </div>

        <div
          v-if="loadingSearch"
          class="empty-state glass-card"
        >
          <q-spinner-dots
            color="primary"
            size="42px"
          />
          <div class="muted-copy">Buscando artículos en el catálogo...</div>
        </div>

        <div
          v-else-if="searchResults.length === 0"
          class="empty-state glass-card"
        >
          <q-icon
            name="search_off"
            size="48px"
            color="secondary"
          />
          <div class="text-subtitle1 text-brand-title">No encontramos coincidencias</div>
          <div class="muted-copy">
            Prueba con otro término o navega por las revistas disponibles.
          </div>
        </div>

        <div
          v-else
          class="column q-gutter-sm"
        >
          <q-card
            v-for="result in searchResults"
            :key="result.id"
            class="glass-card"
          >
            <q-card-section class="row items-start q-col-gutter-md">
              <div class="col-12 col-sm">
                <div class="text-overline text-secondary">
                  {{ result.sourceName || displaySourceName(result.source || '') || 'Colección universitaria' }}
                </div>
                <div class="text-h6 text-brand-title q-mt-xs">{{ result.title }}</div>
                <div class="muted-copy q-mt-xs">
                  {{ result.authorsString || result.authors.join(', ') || 'Autoría no disponible' }}
                </div>
                <div class="muted-copy q-mt-sm ellipsis-2-lines">
                  {{ result.abstract || `${result.journalName || 'Revista'} • ${result.issueTitle || 'Edición'}` }}
                </div>

                <div class="row q-col-gutter-sm q-mt-md">
                  <div class="col-auto">
                    <q-chip
                      dense
                      outline
                      color="secondary"
                      class="pill-chip"
                    >
                      {{ result.journalName || 'Revista' }}
                    </q-chip>
                  </div>
                  <div
                    v-if="result.year"
                    class="col-auto"
                  >
                    <q-chip
                      dense
                      outline
                      color="accent"
                      class="pill-chip"
                    >
                      {{ result.year }}
                    </q-chip>
                  </div>
                </div>
              </div>

              <div class="col-12 col-sm-auto result-action">
                <q-btn
                  unelevated
                  rounded
                  color="primary"
                  icon="picture_as_pdf"
                  label="Abrir PDF"
                  :disable="!result.pdf"
                  class="full-width-mobile"
                  @click="openArticle(result.id)"
                />
              </div>
            </q-card-section>
          </q-card>
        </div>
      </section>

      <q-card class="glass-card">
        <q-card-section class="column q-gutter-md">
          <div>
            <div class="text-subtitle1 text-brand-title">Explorar por colección</div>
            <div class="muted-copy q-mt-xs">
              Elige una colección, abre una revista y luego revisa sus números disponibles.
            </div>
          </div>

          <q-select
            v-model="selectedSourceSlug"
            :options="sourceOptions"
            emit-value
            map-options
            outlined
            label="Colección"
            :loading="loadingSources"
          />
        </q-card-section>
      </q-card>

      <section ref="journalsSectionRef">
        <div class="section-title">
          <h2>Revistas</h2>
          <q-btn
            flat
            round
            color="primary"
            icon="refresh"
            :loading="loadingJournals"
            @click="refreshCurrentSource"
          />
        </div>

        <div
          v-if="loadingJournals"
          class="empty-state glass-card"
        >
          <q-spinner-dots
            color="secondary"
            size="42px"
          />
          <div class="muted-copy">
            Cargando revistas de {{ selectedSourceLabel || 'la colección seleccionada' }}...
          </div>
        </div>

        <div
          v-else-if="journals.length === 0"
          class="empty-state glass-card"
        >
          <q-icon
            name="library_books"
            size="48px"
            color="primary"
          />
          <div class="text-subtitle1 text-brand-title">No hay revistas disponibles</div>
          <div class="muted-copy">Prueba con otra colección o actualiza la pantalla.</div>
        </div>

        <div
          v-else
          class="column q-gutter-sm"
        >
          <q-card
            v-for="journal in journals"
            :key="journal.id"
            class="glass-card journal-card"
            :class="{ 'ring-primary': journal.id === selectedJournalId }"
          >
            <q-card-section class="row items-center q-col-gutter-md">
              <div class="col-12 col-sm">
                <div class="text-overline text-secondary">{{ displaySourceName(journal.source) }}</div>
                <div class="text-h6 text-brand-title q-mt-xs">{{ journal.name }}</div>
                <div class="muted-copy ellipsis-2-lines">
                  {{ journal.description || 'Explora esta revista para ver sus números y artículos publicados.' }}
                </div>
              </div>

              <div class="col-12 col-sm-auto journal-card__action">
                <q-btn
                  unelevated
                  rounded
                  color="primary"
                  icon="menu_book"
                  label="Ver números"
                  class="full-width-mobile"
                  @click="selectJournal(journal.id)"
                />
              </div>
            </q-card-section>
          </q-card>
        </div>
      </section>

      <section
        v-if="selectedJournal"
        ref="issuesSectionRef"
      >
        <div class="section-title">
          <div>
            <h2>Números</h2>
            <div class="muted-copy q-mt-xs">{{ selectedJournal.name }}</div>
          </div>
          <q-chip
            color="secondary"
            text-color="white"
            class="pill-chip"
          >
            {{ issues.length }} disponibles
          </q-chip>
        </div>

        <div
          v-if="loadingIssues"
          class="empty-state glass-card"
        >
          <q-spinner-rings
            color="accent"
            size="42px"
          />
          <div class="muted-copy">Cargando números publicados...</div>
        </div>

        <div
          v-else
          class="row q-col-gutter-sm"
        >
          <div
            v-for="issue in issues"
            :key="issue.id"
            class="col-12 col-sm-6"
          >
            <q-card
              class="glass-card full-height issue-card"
              :class="{ 'ring-primary': issue.id === selectedIssueId }"
            >
              <q-card-section class="column q-gutter-sm">
                <div class="text-overline text-secondary">{{ issue.year || 'Edición' }}</div>
                <div class="text-subtitle1 text-brand-title">{{ issue.title }}</div>
                <div class="muted-copy ellipsis-3-lines">
                  {{ issue.description || 'Abre este número para leer sus artículos o revisar el PDF completo.' }}
                </div>
              </q-card-section>

              <q-card-actions class="issue-card__actions">
                <q-btn
                  unelevated
                  rounded
                  color="primary"
                  icon="article"
                  label="Ver artículos"
                  class="full-width-mobile"
                  @click="selectIssue(issue.id)"
                />
                <q-btn
                  v-if="issue.pdf"
                  flat
                  rounded
                  color="secondary"
                  icon="picture_as_pdf"
                  label="Abrir PDF"
                  class="full-width-mobile"
                  @click="openIssue(issue)"
                />
              </q-card-actions>
            </q-card>
          </div>
        </div>
      </section>

      <section
        v-if="selectedIssue"
        ref="articlesSectionRef"
      >
        <div class="section-title articles-title">
          <div>
            <h2>Artículos</h2>
            <div class="muted-copy q-mt-xs">{{ selectedIssue.title }}</div>
          </div>

          <div class="articles-title__actions">
            <q-chip
              dense
              color="primary"
              text-color="white"
              class="pill-chip"
            >
              {{ articles.length }}
            </q-chip>
            <q-btn
              v-if="selectedIssue.pdf"
              unelevated
              rounded
              color="secondary"
              icon="menu_book"
              label="Abrir número completo"
              @click="openIssue(selectedIssue)"
            />
          </div>
        </div>

        <div
          v-if="loadingArticles"
          class="empty-state glass-card"
        >
          <q-spinner-cube
            color="primary"
            size="42px"
          />
          <div class="muted-copy">Preparando la lista de artículos...</div>
        </div>

        <div
          v-else-if="articles.length === 0"
          class="empty-state glass-card"
        >
          <q-icon
            name="article"
            size="48px"
            color="secondary"
          />
          <div class="text-subtitle1 text-brand-title">Aún no hay artículos visibles</div>
          <div class="muted-copy">Actualiza la edición o prueba más tarde.</div>
        </div>

        <div
          v-else
          class="column q-gutter-sm"
        >
          <q-card
            v-for="article in articles"
            :key="article.id"
            class="glass-card"
          >
            <q-card-section class="row items-start q-col-gutter-md">
              <div class="col-12 col-sm">
                <div class="text-h6 text-brand-title">{{ article.title }}</div>
                <div class="muted-copy q-mt-xs">
                  {{ article.authorsString || article.authors.join(', ') || 'Autoría no disponible' }}
                </div>
                <div class="muted-copy q-mt-sm ellipsis-3-lines">
                  {{ article.abstract || 'Sin resumen disponible.' }}
                </div>
                <div class="row q-col-gutter-sm q-mt-md">
                  <div
                    v-if="article.doi"
                    class="col-auto"
                  >
                    <q-chip
                      dense
                      outline
                      color="secondary"
                      class="pill-chip"
                    >
                      DOI
                    </q-chip>
                  </div>
                  <div
                    v-if="article.pdf"
                    class="col-auto"
                  >
                    <q-chip
                      dense
                      color="accent"
                      text-color="white"
                      class="pill-chip"
                    >
                      PDF disponible
                    </q-chip>
                  </div>
                </div>
              </div>
              <div class="col-12 col-sm-auto article-card__action">
                <q-btn
                  unelevated
                  rounded
                  color="primary"
                  icon="picture_as_pdf"
                  label="Abrir PDF"
                  :disable="!article.pdf"
                  class="full-width-mobile"
                  @click="openArticle(article.id)"
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
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useQuasar } from 'quasar';
import { useRoute, useRouter } from 'vue-router';
import {
  getArticles,
  getIssues,
  getJournals,
  getSources,
  searchCatalog,
} from 'src/services/ojs-api';
import type { ArticleSummary, IssueSummary, JournalSummary, SearchResult, SourceSummary } from 'src/types/ojs';

const $q = useQuasar();
const route = useRoute();
const router = useRouter();

const sources = ref<SourceSummary[]>([]);
const journals = ref<JournalSummary[]>([]);
const issues = ref<IssueSummary[]>([]);
const articles = ref<ArticleSummary[]>([]);
const searchResults = ref<SearchResult[]>([]);

const selectedSourceSlug = ref<string>((route.query.source as string) || '');
const selectedJournalId = ref('');
const selectedIssueId = ref('');
const searchExpanded = ref(false);

const searchQuery = ref('');
const searchSourceSlug = ref<string | null>(null);
const searchYear = ref<number | null>(null);

const loadingSources = ref(false);
const loadingJournals = ref(false);
const loadingIssues = ref(false);
const loadingArticles = ref(false);
const loadingSearch = ref(false);

const journalsSectionRef = ref<HTMLElement | null>(null);
const issuesSectionRef = ref<HTMLElement | null>(null);
const articlesSectionRef = ref<HTMLElement | null>(null);

const sourceOptions = computed(() => sources.value.map((source) => ({
  label: source.name,
  value: source.slug,
})));

const searchSourceOptions = computed(() => [
  { label: 'Todas las colecciones', value: null },
  ...sourceOptions.value,
]);

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  return Array.from({ length: 15 }, (_, index) => currentYear - index).map((year) => ({
    label: String(year),
    value: year,
  }));
});

const selectedSourceLabel = computed(() => (
  sources.value.find((source) => source.slug === selectedSourceSlug.value)?.name || ''
));
const selectedJournal = computed(() => journals.value.find((journal) => journal.id === selectedJournalId.value) || null);
const selectedIssue = computed(() => issues.value.find((issue) => issue.id === selectedIssueId.value) || null);
const hasSearchContext = computed(() => searchQuery.value.trim() !== '' || searchSourceSlug.value !== null || searchYear.value !== null);

function displaySourceName(sourceSlug: string) {
  return sources.value.find((source) => source.slug === sourceSlug)?.name || sourceSlug || 'Colección universitaria';
}

function scrollToSection(target: HTMLElement | null) {
  if (!target) {
    return;
  }

  target.scrollIntoView({
    behavior: 'smooth',
    block: 'start',
  });
}

async function loadSources() {
  loadingSources.value = true;

  try {
    sources.value = await getSources();

    if (!selectedSourceSlug.value && sources.value.length > 0) {
      selectedSourceSlug.value = sources.value[0]?.slug || '';
    }
  } catch (error) {
    notifyError(error, 'No pudimos cargar las colecciones disponibles.');
  } finally {
    loadingSources.value = false;
  }
}

async function loadJournalsForSource(sourceSlug: string) {
  journals.value = [];
  issues.value = [];
  articles.value = [];
  selectedJournalId.value = '';
  selectedIssueId.value = '';

  if (!sourceSlug) {
    return;
  }

  loadingJournals.value = true;

  try {
    journals.value = await getJournals(sourceSlug);
    await nextTick();
    scrollToSection(journalsSectionRef.value);
  } catch (error) {
    notifyError(error, 'No pudimos cargar la lista de revistas.');
  } finally {
    loadingJournals.value = false;
  }
}

async function selectJournal(journalId: string) {
  selectedJournalId.value = journalId;
  selectedIssueId.value = '';
  issues.value = [];
  articles.value = [];
  loadingIssues.value = true;

  try {
    issues.value = await getIssues(journalId);
    await nextTick();
    scrollToSection(issuesSectionRef.value);
  } catch (error) {
    notifyError(error, 'No pudimos cargar los números de esta revista.');
  } finally {
    loadingIssues.value = false;
  }
}

async function selectIssue(issueId: string) {
  selectedIssueId.value = issueId;
  articles.value = [];
  loadingArticles.value = true;

  try {
    articles.value = await getArticles(issueId);
    await nextTick();
    scrollToSection(articlesSectionRef.value);
  } catch (error) {
    notifyError(error, 'No pudimos cargar los artículos de este número.');
  } finally {
    loadingArticles.value = false;
  }
}

async function runSearch() {
  if (!hasSearchContext.value) {
    searchResults.value = [];
    return;
  }

  loadingSearch.value = true;
  searchExpanded.value = true;

  try {
    searchResults.value = await searchCatalog(
      searchQuery.value.trim(),
      searchSourceSlug.value,
      searchYear.value,
    );
  } catch (error) {
    notifyError(error, 'No pudimos completar la búsqueda.');
  } finally {
    loadingSearch.value = false;
  }
}

function clearSearch() {
  searchQuery.value = '';
  searchSourceSlug.value = null;
  searchYear.value = null;
  searchResults.value = [];
}

async function refreshCurrentSource() {
  await loadJournalsForSource(selectedSourceSlug.value);
}

function openArticle(articleId: string) {
  void router.push({
    name: 'reader',
    params: {
      documentType: 'article',
      documentId: articleId,
    },
  });
}

function openIssue(issue: IssueSummary) {
  if (!issue.pdf) {
    return;
  }

  void router.push({
    name: 'reader',
    params: {
      documentType: 'issue',
      documentId: issue.id,
    },
    query: {
      title: issue.title,
      filename: issue.pdf.filename,
    },
  });
}

function notifyError(error: unknown, fallbackMessage: string) {
  $q.notify({
    type: 'negative',
    message: error instanceof Error ? error.message : fallbackMessage,
  });
}

watch(hasSearchContext, (nextValue) => {
  if (nextValue) {
    searchExpanded.value = true;
  }
});

watch(
  selectedSourceSlug,
  async (nextSource) => {
    if (!nextSource) {
      return;
    }

    void router.replace({
      name: 'journals',
      query: { source: nextSource },
    });

    await loadJournalsForSource(nextSource);
  },
);

onMounted(async () => {
  await loadSources();

  if (selectedSourceSlug.value) {
    await loadJournalsForSource(selectedSourceSlug.value);
  }
});
</script>

<style scoped>
.catalog-hero {
  padding-block: 4px;
}

.search-card {
  overflow: hidden;
}

.search-card__header {
  min-height: 78px;
}

.search-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.journal-card__action,
.article-card__action,
.result-action {
  display: flex;
  align-items: center;
}

.issue-card {
  display: flex;
  flex-direction: column;
}

.issue-card__actions {
  padding: 0 16px 16px;
  gap: 10px;
}

.articles-title {
  align-items: flex-start;
}

.articles-title__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.ring-primary {
  box-shadow:
    0 0 0 2px rgba(102, 51, 153, 0.18),
    var(--unitepc-soft-shadow);
}

@media (max-width: 599px) {
  .search-actions,
  .articles-title__actions {
    width: 100%;
  }

  .search-actions :deep(.q-btn),
  .articles-title__actions :deep(.q-btn),
  .issue-card__actions :deep(.q-btn),
  .full-width-mobile {
    width: 100%;
  }
}
</style>
