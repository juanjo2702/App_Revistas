import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      {
        path: '',
        name: 'home',
        component: () => import('pages/HomePage.vue'),
      },
      {
        path: 'journals',
        name: 'journals',
        component: () => import('pages/JournalsPage.vue'),
      },
      {
        path: 'downloads',
        name: 'downloads',
        component: () => import('pages/DownloadsPage.vue'),
      },
      {
        path: 'preferences',
        name: 'preferences',
        component: () => import('pages/PreferencesPage.vue'),
      },
      {
        path: 'reader/:documentType/:documentId',
        name: 'reader',
        component: () => import('pages/PdfReaderPage.vue'),
        props: true,
        meta: {
          hideTabs: true,
        },
      },
    ],
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue'),
  },
];

export default routes;
