import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';
import { PushNotifications, type Token } from '@capacitor/push-notifications';
import type { Router } from 'vue-router';

const PUSH_TOKEN_KEY = 'unitepc-push-token';
let listenersBound = false;

interface NotificationRoutePayload {
  route?: string;
  source?: string;
  journal?: string;
}

function notificationErrorMessage() {
  return 'No pudimos registrar este dispositivo para recibir alertas.';
}

async function ensureAndroidChannel() {
  if (Capacitor.getPlatform() !== 'android') {
    return;
  }

  await PushNotifications.createChannel({
    id: 'catalog-updates',
    name: 'Novedades de revistas',
    description: 'Alertas sobre nuevas publicaciones y actualizaciones del catálogo.',
    importance: 4,
    visibility: 1,
  }).catch(() => undefined);
}

function resolveNavigationTarget(data?: NotificationRoutePayload) {
  if (!data?.route) {
    return null;
  }

  if (data.route === 'journals') {
    const query: Record<string, string> = {};

    if (typeof data.source === 'string' && data.source !== '') {
      query.source = data.source;
    }

    if (typeof data.journal === 'string' && data.journal !== '') {
      query.journal = data.journal;
    }

    return {
      name: 'journals',
      query,
    };
  }

  return null;
}

function waitForRegistration(): Promise<string> {
  return new Promise((resolve, reject) => {
    let registrationHandle: Awaited<ReturnType<typeof PushNotifications.addListener>> | null = null;
    let errorHandle: Awaited<ReturnType<typeof PushNotifications.addListener>> | null = null;

    const cleanup = async () => {
      await registrationHandle?.remove();
      await errorHandle?.remove();
    };

    void PushNotifications.addListener('registration', (token: Token) => {
      void (async () => {
        await Preferences.set({ key: PUSH_TOKEN_KEY, value: token.value });
        await cleanup();
        resolve(token.value);
      })();
    }).then((handle) => {
      registrationHandle = handle;
    });

    void PushNotifications.addListener('registrationError', (error) => {
      void (async () => {
        await cleanup();
        reject(new Error(error.error ?? notificationErrorMessage()));
      })();
    }).then((handle) => {
      errorHandle = handle;
    });
  });
}

export async function setupPushListeners(router?: Router) {
  if (!Capacitor.isNativePlatform() || listenersBound) {
    return;
  }

  listenersBound = true;
  await ensureAndroidChannel();

  await PushNotifications.addListener('pushNotificationReceived', (notification) => {
    console.info('Push notification received', notification);
  });

  await PushNotifications.addListener('pushNotificationActionPerformed', (notification) => {
    console.info('Push notification action performed', notification);

    const target = resolveNavigationTarget(notification.notification.data as NotificationRoutePayload | undefined);

    if (router && target) {
      void router.push(target);
    }
  });
}

export async function getStoredPushToken() {
  const { value } = await Preferences.get({ key: PUSH_TOKEN_KEY });
  return value || null;
}

export async function enablePushNotifications() {
  if (!Capacitor.isNativePlatform()) {
    throw new Error('Las alertas solo están disponibles en Android o iPhone.');
  }

  await ensureAndroidChannel();

  const current = await PushNotifications.checkPermissions();
  const permissions = current.receive === 'granted'
    ? current
    : await PushNotifications.requestPermissions();

  if (permissions.receive !== 'granted') {
    throw new Error('No se concedió el permiso para enviar alertas.');
  }

  const registration = waitForRegistration();
  await PushNotifications.register();

  return registration;
}
