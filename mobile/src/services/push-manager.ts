import { Capacitor } from '@capacitor/core';
import { PushNotifications, type Token } from '@capacitor/push-notifications';
import { Preferences } from '@capacitor/preferences';

const PUSH_TOKEN_KEY = 'unitepc-push-token';
let listenersBound = false;

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
        reject(new Error(error.error ?? 'No se pudo registrar el dispositivo para notificaciones.'));
      })();
    }).then((handle) => {
      errorHandle = handle;
    });
  });
}

export async function setupPushListeners() {
  if (!Capacitor.isNativePlatform() || listenersBound) {
    return;
  }

  listenersBound = true;

  await PushNotifications.addListener('pushNotificationReceived', (notification) => {
    console.info('Push notification received', notification);
  });

  await PushNotifications.addListener('pushNotificationActionPerformed', (notification) => {
    console.info('Push notification action performed', notification);
  });
}

export async function getStoredPushToken() {
  const { value } = await Preferences.get({ key: PUSH_TOKEN_KEY });
  return value || null;
}

export async function enablePushNotifications() {
  if (!Capacitor.isNativePlatform()) {
    throw new Error('Las notificaciones push solo están disponibles en Android o iOS.');
  }

  await setupPushListeners();

  const current = await PushNotifications.checkPermissions();
  const permissions = current.receive === 'granted'
    ? current
    : await PushNotifications.requestPermissions();

  if (permissions.receive !== 'granted') {
    throw new Error('El permiso de notificaciones fue rechazado.');
  }

  const registration = waitForRegistration();
  await PushNotifications.register();

  return registration;
}
