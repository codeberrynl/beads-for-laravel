import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { config } from '@/lib/config'

export type ListView = 'all' | 'active' | 'backlog' | 'mine' | 'ready' | 'blocked'

declare module 'vue-router' {
  interface RouteMeta {
    view?: ListView
  }
}

const IssuesPage = () => import('@/pages/IssuesPage.vue')

const routes: RouteRecordRaw[] = [
  { path: '/', redirect: '/issues' },
  { path: '/issues', name: 'issues', component: IssuesPage, meta: { view: 'all' } },
  { path: '/issues/active', name: 'issues.active', component: IssuesPage, meta: { view: 'active' } },
  { path: '/issues/backlog', name: 'issues.backlog', component: IssuesPage, meta: { view: 'backlog' } },
  { path: '/my-issues', name: 'mine', component: IssuesPage, meta: { view: 'mine' } },
  { path: '/ready', name: 'ready', component: IssuesPage, meta: { view: 'ready' } },
  { path: '/blocked', name: 'blocked', component: IssuesPage, meta: { view: 'blocked' } },
  { path: '/board', name: 'board', component: () => import('@/pages/BoardPage.vue') },
  { path: '/epics', name: 'epics', component: () => import('@/pages/EpicsPage.vue') },
  { path: '/issue/:id', name: 'issue', component: () => import('@/pages/IssuePage.vue'), props: true },
  { path: '/_ui', name: 'ui', component: () => import('@/pages/UiGalleryPage.vue') },
  { path: '/:pathMatch(.*)*', redirect: '/issues' },
]

export const router = createRouter({
  history: createWebHistory(`${config.basePath}/`),
  routes,
})
