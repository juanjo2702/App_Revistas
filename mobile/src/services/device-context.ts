import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';
import type {
  DevicePreferencePayload,
  DevicePreferenceState,
  DeviceRegistrationPayload,
} from 'src/types/ojs';
import { getDevicePreferences, registerDevice, updateDevicePreferences } from './ojs-api';

const DEVICE_ID_KEY = 'unitepc-device-id';
const DEVICE_PREFS_CACHE_KEY = 'unitepc-device-preferences';

function fallbackUuid() {
  return `unitepc-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
}

export async function getOrCreateDeviceId() {
  const current = await Preferences.get({ key: DEVICE_ID_KEY });

  if (current.value) {
    return current.value;
  }

  const next = typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
    ? crypto.randomUUID()
    : fallbackUuid();

  await Preferences.set({ key: DEVICE_ID_KEY, value: next });

  return next;
}

export async function buildRegistrationPayload(pushToken?: string | null, notificationsEnabled = false): Promise<DeviceRegistrationPayload> {
  const deviceId = await getOrCreateDeviceId();
  const appInfo = await App.getInfo().catch(() => null);

  return {
    deviceId,
    platform: Capacitor.getPlatform(),
    appVersion: appInfo?.version ?? null,
    locale: typeof navigator !== 'undefined' ? navigator.language : null,
    pushToken: pushToken ?? null,
    notificationsEnabled,
    meta: {
      appBuild: appInfo?.build ?? null,
      isNative: Capacitor.isNativePlatform(),
    },
  };
}

export async function initializeDeviceContext() {
  const payload = await buildRegistrationPayload(null, false);

  try {
    await registerDevice(payload);
  } catch {
    // Keep startup resilient even if the backend is temporarily unavailable.
  }
}

export async function loadDevicePreferences(): Promise<DevicePreferenceState> {
  const deviceId = await getOrCreateDeviceId();

  try {
    const remote = await getDevicePreferences(deviceId);
    await Preferences.set({
      key: DEVICE_PREFS_CACHE_KEY,
      value: JSON.stringify(remote),
    });

    return remote;
  } catch {
    const cached = await Preferences.get({ key: DEVICE_PREFS_CACHE_KEY });

    if (cached.value) {
      return JSON.parse(cached.value) as DevicePreferenceState;
    }

    const registration = await buildRegistrationPayload();

    return {
      deviceId,
      platform: registration.platform,
      appVersion: registration.appVersion ?? null,
      locale: registration.locale ?? null,
      notificationsEnabled: false,
      pushConfigured: false,
      followedSources: [],
      followedJournals: [],
      followedYears: [],
      updatedAt: null,
    };
  }
}

export async function persistDevicePreferences(input: Omit<DevicePreferencePayload, 'deviceId' | 'platform' | 'appVersion' | 'locale'>) {
  const registration = await buildRegistrationPayload(input.pushToken, input.notificationsEnabled);
  const payload: DevicePreferencePayload = {
    ...registration,
    followedSources: input.followedSources,
    followedJournals: input.followedJournals,
    followedYears: input.followedYears,
  };

  const saved = await updateDevicePreferences(payload);

  await Preferences.set({
    key: DEVICE_PREFS_CACHE_KEY,
    value: JSON.stringify(saved),
  });

  return saved;
}
