<template>
  <q-page class="page-frame">
    <div class="column q-gutter-md">
      <q-card class="glass-card">
        <q-card-section>
          <div class="text-overline text-secondary">Catálogo móvil</div>
          <div class="text-h5 text-brand-title q-mt-xs">Revistas, números y artículos</div>
          <div class="muted-copy q-mt-sm">
            Explora por fuente OJS o usa la búsqueda avanzada para encontrar artículos por título,
            autor, DOI o año.
          </div>
        </q-card-section>
      </q-card>

      <q-card class="glass-card">
        <q-card-section class="column q-gutter-md">
          <div class="text-subtitle1 text-brand-title">Búsqueda avanzada</div>

          <q-input
            v-model="searchQuery"
            outlined
            clearable
            label="Buscar por título, autor o DOI"
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
                label="Filtrar por fuente"
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
                label="Filtrar por año"
              />
            </div>
          </div>

          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-auto">
              <q-btn
                unelevated
                rounded
                color="primary"
                icon="travel_explore"
                :loading="loadingSearch"
                label="Buscar"
                @click="runSearch"
              />
            </div>
            <div class="col-12 col-sm-auto">
              <q-btn
                flat
                rounded
                color="secondary"
                icon="cleaning_services"
                label="Limpiar"
                @click="clearSearch"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <section v-if="hasSearchContext">
        <div class="section-title">
          <h2>Resultados de búsqueda</h2>
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
          <div class="muted-copy">Consultando el índice local de búsqueda...</div>
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
          <div class="text-subtitle1 text-brand-title">Sin coincidencias</div>
          <div class="muted-copy">
            Ajusta tu consulta o navega por la estructura editorial debajo.
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
                  {{ result.sourceName || result.source || 'Fuente' }}
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

              <div class="col-12 col-sm-auto">
                <q-btn
                  unelevated
                  rounded
                  color="primary"
                  icon="picture_as_pdf"
                  label="Leer"
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
        <q-card-section>
          <div class="text-subtitle1 text-brand-title">Explorar por fuente</div>
          <div class="muted-copy q-mt-sm">
            Selecciona una fuente OJS, luego una revista y finalmente una edición.
          </div>
        </q-card-section>

        <q-separator inset />

        <q-card-section>
          <q-select
            v-model="selectedSourceSlug"
            :options="sourceOptions"
            emit-value
            map-options
            outlined
            label="Fuente editorial"
            :loading="loadingSources"
          />
        </q-card-section>
      </q-card>

      <section>
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
          <div class="muted-copy">Consultando revistas en {{ selectedSourceSlug || 'la fuente elegida' }}...</div>
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
          <div class="text-subtitle1 text-brand-title">No hay revistas para mostrar todavía</div>
          <div class="muted-copy">Prueba otra fuente o revisa la configuración del bridge.</div>
        </div>

        <div
          v-else
          class="column q-gutter-sm"
        >
          <q-card
            v-for="journal in journals"
            :key="journal.id"
            class="glass-card"
            :class="{ 'ring-primary': journal.id === selectedJournalId }"
            clickable
            @click="selectJournal(journal.id)"
          >
            <q-card-section class="row items-center q-col-gutter-md">
              <div class="col-12 col-sm">
                <div class="text-overline text-secondary">{{ journal.source }}</div>
                <div class="text-h6 text-brand-title q-mt-xs">{{ journal.name }}</div>
                <div class="muted-copy ellipsis-2-lines">
                  {{ journal.description || 'Sin descripción disponible.' }}
                </div>
              </div>

              <div class="col-12 col-sm-auto">
                <q-chip
                  color="secondary"
                  text-color="white"
                  class="pill-chip"
                >
                  Abrir números
                </q-chip>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </section>

      <section v-if="selectedJournal">
        <div class="section-title">
          <h2>Números de {{ selectedJournal.name }}</h2>
        </div>

        <div
          v-if="loadingIssues"
          class="empty-state glass-card"
        >
          <q-spinner-rings
            color="accent"
            size="42px"
          />
          <div class="muted-copy">Cargando números y ediciones...</div>
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
              class="glass-card full-height"
              clickable
              @click="selectIssue(issue.id)"
            >
              <q-card-section>
                <div class="text-overline text-secondary">{{ issue.year || 'Edición' }}</div>
                <div class="text-subtitle1 text-brand-title q-mt-xs">{{ issue.title }}</div>
                <div class="muted-copy q-mt-sm">
                  {{ issue.description || 'Abre esta edición para ver sus artículos y PDF asociados.' }}
                </div>
              </q-card-section>
            </q-card>
          </div>
        </div>
      </section>

      <section v-if="selectedIssue">
        <div class="section-title">
          <h2>Artículos</h2>
          <div class="row q-gutter-sm items-center">
            <q-chip
              dense
              color="primary"
              text-color="white"
              class="pill-chip"
            >
              {{ articles.length }} resultados
            </q-chip>
            <q-btn
              v-if="selectedIssue?.pdf"
              unelevated
              rounded
              color="secondary"
              icon="menu_book"
              label="Leer nÃºmero completo"
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
          <div class="muted-copy">Armando la tabla de contenidos...</div>
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
          <div class="text-subtitle1 text-brand-title">Sin artículos visibles</div>
          <div class="muted-copy">Esta edición no devolvió artículos desde la API puente.</div>
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
                      PDF listo
                    </q-chip>
                  </div>
                </div>
              </div>
              <div class="col-12 col-sm-auto">
                <q-btn
                  unelevated
                  rounded
                  color="primary"
                  icon="picture_as_pdf"
                  label="Leer"
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
import { computed, onMounted, ref, watch } from 'vue';
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

const searchQuery = ref('');
const searchSourceSlug = ref<string | null>(null);
const searchYear = ref<number | null>(null);

const loadingSources = ref(false);
const loadingJournals = ref(false);
const loadingIssues = ref(false);
const loadingArticles = ref(false);
const loadingSearch = ref(false);

const sourceOptions = computed(() => sources.value.map((source) => ({
  label: source.name,
  value: source.slug,
})));

const searchSourceOptions = computed(() => [
  { label: 'Todas las fuentes', value: null },
  ...sourceOptions.value,
]);

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  return Array.from({ length: 15 }, (_, index) => currentYear - index).map((year) => ({
    label: String(year),
    value: year,
  }));
});

const selectedJournal = computed(() => journals.value.find((journal) => journal.id === selectedJournalId.value) || null);
const selectedIssue = computed(() => issues.value.find((issue) => issue.id === selectedIssueId.value) || null);
const hasSearchContext = computed(() => searchQuery.value.trim() !== '' || searchSourceSlug.value !== null || searchYear.value !== null);

async function loadSources() {
  loadingSources.value = true;

  try {
    sources.value = await getSources();

    if (!selectedSourceSlug.value && sources.value.length > 0) {
      selectedSourceSlug.value = sources.value[0]?.slug || '';
    }
  } catch (error) {
    notifyError(error, 'No se pudieron cargar las fuentes.');
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
  } catch (error) {
    notifyError(error, 'No se pudo cargar la lista de revistas.');
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
  } catch (error) {
    notifyError(error, 'No se pudieron cargar los números de la revista.');
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
  } catch (error) {
    notifyError(error, 'No se pudieron cargar los artículos de la edición.');
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

  try {
    searchResults.value = await searchCatalog(
      searchQuery.value.trim(),
      searchSourceSlug.value,
      searchYear.value,
    );
  } catch (error) {
    notifyError(error, 'No se pudo ejecutar la búsqueda.');
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
.ring-primary {
  box-shadow:
    0 0 0 2px rgba(102, 51, 153, 0.18),
    var(--unitepc-soft-shadow);
}

@media (max-width: 599px) {
  .full-width-mobile {
    width: 100%;
  }
}
</style>
