<template>
  <Teleport to="body">
    <Transition name="command-palette-fade">
      <section v-if="state.isOpen" class="command-palette-overlay" @click="onBackdrop">
        <Transition name="command-palette-scale" appear>
          <div class="command-palette-overlay__dialog" @click.stop>
            <BaseCommandPalette
              :query="state.query"
              :items="items"
              :active-item-id="activeItemId"
              :loading="state.loading"
              @close="close"
              @move-next="moveNext"
              @move-prev="movePrev"
              @execute="execute"
              @execute-by-id="executeById"
              @set-query="setQuery"
              @set-active-by-id="setActiveById"
            />
          </div>
        </Transition>
      </section>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import BaseCommandPalette from './BaseCommandPalette.vue';
import { commandPaletteStore } from '../stores/command-palette.store';

const state = commandPaletteStore.state;
const items = computed(() => commandPaletteStore.items.value);

const activeItemId = computed(() => items.value[state.activeIndex]?.id ?? '');

const close = (): void => {
  commandPaletteStore.close();
};

const moveNext = (): void => {
  commandPaletteStore.moveNext();
};

const movePrev = (): void => {
  commandPaletteStore.movePrev();
};

const execute = async (): Promise<void> => {
  await commandPaletteStore.executeActive();
};

const executeById = async (id: string): Promise<void> => {
  const index = items.value.findIndex((item) => item.id === id);
  if (index < 0) return;
  state.activeIndex = index;
  await execute();
};

const setQuery = (value: string): void => {
  commandPaletteStore.setQuery(value);
};

const setActiveById = (id: string): void => {
  const index = items.value.findIndex((item) => item.id === id);
  if (index >= 0) {
    state.activeIndex = index;
  }
};

const onBackdrop = (): void => {
  close();
};
</script>

<style scoped>
.command-palette-overlay{position:fixed;inset:0;z-index:2050;background:rgba(2,6,23,.58);backdrop-filter:blur(3px);display:flex;align-items:flex-start;justify-content:center;padding-top:min(14vh,120px);padding-inline:12px}
.command-palette-overlay__dialog{max-width:100%;}
.command-palette-fade-enter-active,.command-palette-fade-leave-active{transition:opacity .18s ease}
.command-palette-fade-enter-from,.command-palette-fade-leave-to{opacity:0}
.command-palette-scale-enter-active,.command-palette-scale-leave-active{transition:transform .2s ease,opacity .2s ease}
.command-palette-scale-enter-from,.command-palette-scale-leave-to{opacity:0;transform:translateY(8px) scale(.99)}
@media (max-width:720px){.command-palette-overlay{padding-top:8vh}}
</style>
