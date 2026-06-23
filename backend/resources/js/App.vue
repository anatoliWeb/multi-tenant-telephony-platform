<template>
  <!--
    Root app shell.
    Routing + layout composition happens under router views.
  -->
  <router-view v-if="isReady" />
  <AppGlobalLoadingOverlay
    :visible="isGlobalLoading || isBootstrapping || Boolean(bootError)"
    :message="bootError || loadingMessage || 'Loading...'"
  />
  <ToastContainer />
  <ConfirmContainer />
  <CommandPaletteContainer />
  <ModalContainer />
  <DrawerContainer />
  <FloatingPanelContainer />
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia';
import CommandPaletteContainer from './shared/command-palette/components/CommandPaletteContainer.vue';
import ConfirmContainer from './shared/confirm/components/ConfirmContainer.vue';
import DrawerContainer from './shared/drawer/components/DrawerContainer.vue';
import FloatingPanelContainer from './shared/floating-panel/components/FloatingPanelContainer.vue';
import ModalContainer from './shared/modal/components/ModalContainer.vue';
import ToastContainer from './shared/toast/components/ToastContainer.vue';
import AppGlobalLoadingOverlay from './shared/components/system/AppGlobalLoadingOverlay.vue';
import { useCommandPalette } from './shared/command-palette/composables/useCommandPalette';
import { useBootstrapStore } from './stores/bootstrap.store';
import { useGlobalLoadingStore } from './stores/global-loading.store';

useCommandPalette();
const bootstrapStore = useBootstrapStore();
const globalLoadingStore = useGlobalLoadingStore();
const { isBootstrapping, isReady, bootError } = storeToRefs(bootstrapStore);
const { isGlobalLoading, loadingMessage } = storeToRefs(globalLoadingStore);
</script>
