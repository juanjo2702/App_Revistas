<template>
  <q-page class="page-frame">
    <div class="column q-gutter-md">
      <q-card class="glass-card">
        <q-card-section>
          <div class="text-overline text-secondary">Preferencias personales</div>
          <div class="text-h5 text-brand-title q-mt-xs">Alertas y colecciones de interés</div>
          <div class="muted-copy q-mt-sm">
            Personaliza tus alertas y selecciona las colecciones que quieres seguir desde este
            dispositivo.
          </div>
        </q-card-section>
      </q-card>

      <q-card class="glass-card">
        <q-card-section class="row q-col-gutter-md items-start">
          <div class="col-12 col-md">
            <div class="text-subtitle1 text-brand-title">Estado de las alertas</div>
            <div class="muted-copy q-mt-sm">{{ statusMessage }}</div>
            <div class="muted-copy q-mt-xs">Plataforma: {{ preferences.platform || 'web' }}</div>
            <div class="muted-copy">Versión: {{ preferences.appVersion || 'sin dato' }}</div>
          </div>

          <div class="col-12 col-md-auto">
            <q-chip
              :color="preferences.pushConfigured ? 'positive' : 'warning'"
              text-color="white"
              class="pill-chip"
            >
              {{ preferences.pushConfigured ? 'Alertas activas' : 'Alertas pendientes' }}
            </q-chip>
          </div>
        </q-card-section>

        <q-separator inset />

        <q-card-section class="column q-gutter-md">
          <q-toggle
            v-model="preferences.notificationsEnabled"
            color="primary"
            label="Permitir alertas en este dispositivo"
          />

          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-auto">
              <q-btn
                unelevated
                rounded
                color="primary"
                icon="notifications_active"
                :loading="enablingPush"
                label="Activar alertas"
                @click="activatePush"
              />
            </div>
            <div class="col-12 col-sm-auto">
              <q-btn
                flat
                rounded
                color="secondary"
                icon="refresh"
                :loading="loading"
                label="Recargar"
                @click="loadPage"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <q-card class="glass-card">
        <q-card-section class="column q-gutter-md">
          <q-select
            v-model="preferences.followedSources"
            :options="sourceOptions"
            multiple
            emit-value
            map-options
            use-chips
            outlined
            label="Colecciones seguidas"
            hint="Selecciona las colecciones que quieres priorizar"
          />

          <q-select
            v-model="preferences.followedJournals"
            :options="journalOptions"
            multiple
            emit-value
            map-options
            use-chips
            outlined
            label="Revistas seguidas"
            hint="Se cargan según las colecciones elegidas"
          />

          <q-select
            v-model="preferences.followedYears"
            :options="yearOptions"
            multiple
            emit-value
            map-options
            use-chips
            outlined
            label="Años de interés"
          />

          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-auto">
              <q-btn
                unelevated
                rounded
                color="primary"
                icon="save"
                :loading="saving"
                label="Guardar preferencias"
                @click="savePreferences"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <q-card class="glass-card">
        <q-card-section>
          <div class="text-subtitle1 text-brand-title">Para recibir alertas reales</div>
          <div class="muted-copy q-mt-sm">
            La app ya puede registrar este dispositivo y guardar tus preferencias. Para el envío de
            alertas en producción todavía debes conectar Firebase en Android y cargar las
            credenciales del servidor.
          </div>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useQuasar } from 'quasar';
import { loadDevicePreferences, persistDevicePreferences } from 'src/services/device-context';
import { getJournals, getSources } from 'src/services/ojs-api';
import { enablePushNotifications, getStoredPushToken } from 'src/services/push-manager';
import type { DevicePreferenceState, JournalSummary, SourceSummary } from 'src/types/ojs';

const $q = useQuasar();

const loading = ref(false);
const saving = ref(false);
const enablingPush = ref(false);
const availableSources = ref<SourceSummary[]>([]);
const availableJournals = ref<JournalSummary[]>([]);

const preferences = ref<DevicePreferenceState>({
  deviceId: '',
  platform: '',
  appVersion: '',
  locale: '',
  notificationsEnabled: false,
  pushConfigured: false,
  followedSources: [],
  followedJournals: [],
  followedYears: [],
  updatedAt: null,
});

const sourceOptions = computed(() => availableSources.value.map((source) => ({
  label: source.name,
  value: source.slug,
})));

const journalOptions = computed(() => availableJournals.value.map((journal) => ({
  label: `${journal.name} (${journal.source})`,
  value: journal.id,
})));

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  return Array.from({ length: 12 }, (_, index) => currentYear - index).map((year) => ({
    label: String(year),
    value: year,
  }));
});

const statusMessage = computed(() => {
  if (preferences.value.pushConfigured && preferences.value.notificationsEnabled) {
    return 'Este dispositivo ya está listo para recibir novedades de tus revistas preferidas.';
  }

  if (preferences.value.pushConfigured) {
    return 'El dispositivo ya está enlazado, pero las alertas están desactivadas.';
  }

  return 'Activa las alertas para recibir avisos cuando haya novedades en las colecciones que sigues.';
});

async function loadJournalsForSources(sourceSlugs: string[]) {
  if (sourceSlugs.length === 0) {
    availableJournals.value = [];
    preferences.value.followedJournals = [];
    return;
  }

  const collections = await Promise.all(sourceSlugs.map((sourceSlug) => getJournals(sourceSlug)));
  availableJournals.value = collections.flat();
  const allowedIds = new Set(availableJournals.value.map((journal) => journal.id));
  preferences.value.followedJournals = preferences.value.followedJournals.filter((id) => allowedIds.has(id));
}

async function loadPage() {
  loading.value = true;

  try {
    const [sources, nextPreferences] = await Promise.all([
      getSources(),
      loadDevicePreferences(),
    ]);

    availableSources.value = sources;
    preferences.value = nextPreferences;
    await loadJournalsForSources(nextPreferences.followedSources);
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'No pudimos cargar tus preferencias.',
    });
  } finally {
    loading.value = false;
  }
}

async function activatePush() {
  enablingPush.value = true;

  try {
    const token = await enablePushNotifications();
    preferences.value.pushConfigured = true;
    preferences.value.notificationsEnabled = true;

    await persistDevicePreferences({
      pushToken: token,
      notificationsEnabled: true,
      followedSources: preferences.value.followedSources,
      followedJournals: preferences.value.followedJournals,
      followedYears: preferences.value.followedYears.map(Number),
    });

    $q.notify({
      type: 'positive',
      message: 'Este dispositivo ya quedó listo para recibir alertas.',
    });
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'No pudimos activar las alertas.',
    });
  } finally {
    enablingPush.value = false;
  }
}

async function savePreferences() {
  saving.value = true;

  try {
    const pushToken = await getStoredPushToken();
    preferences.value = await persistDevicePreferences({
      pushToken,
      notificationsEnabled: preferences.value.notificationsEnabled,
      followedSources: preferences.value.followedSources,
      followedJournals: preferences.value.followedJournals,
      followedYears: preferences.value.followedYears.map(Number),
    });

    $q.notify({
      type: 'positive',
      message: 'Tus preferencias se guardaron correctamente.',
    });
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'No pudimos guardar tus preferencias.',
    });
  } finally {
    saving.value = false;
  }
}

watch(
  () => [...preferences.value.followedSources],
  (nextSources) => {
    void loadJournalsForSources(nextSources);
  },
);

void loadPage();
</script>
