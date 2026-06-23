<template>
  <section class="chat-admin-filters c-card">
    <div class="chat-admin-filters__search">
      <label class="chat-admin-filters__label" for="chat-admin-search">Search</label>
      <input
        id="chat-admin-search"
        :value="search"
        class="chat-admin-filters__input"
        type="text"
        placeholder="Search conversations"
        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <div class="chat-admin-filters__field">
      <label class="chat-admin-filters__label">Type</label>
      <select data-testid="filter-type" :value="type" class="chat-admin-filters__select" @change="emit('update:type', ($event.target as HTMLSelectElement).value)">
        <option value="all">All</option>
        <option value="direct">Direct</option>
        <option value="group">Group</option>
        <option value="support">Support</option>
        <option value="external">External</option>
        <option value="system">System</option>
      </select>
    </div>

    <div class="chat-admin-filters__field">
      <label class="chat-admin-filters__label">Status</label>
      <select data-testid="filter-status" :value="status" class="chat-admin-filters__select" @change="emit('update:status', ($event.target as HTMLSelectElement).value)">
        <option value="all">All</option>
        <option value="active">Active</option>
        <option value="archived">Archived</option>
        <option value="closed">Closed</option>
      </select>
    </div>

    <div class="chat-admin-filters__field">
      <label class="chat-admin-filters__label">Visibility</label>
      <select data-testid="filter-visibility" :value="visibility" class="chat-admin-filters__select" @change="emit('update:visibility', ($event.target as HTMLSelectElement).value)">
        <option value="all">All</option>
        <option value="private">Private</option>
        <option value="public">Public</option>
      </select>
    </div>

    <div class="chat-admin-filters__field">
      <label class="chat-admin-filters__label">Source</label>
      <select data-testid="filter-source" :value="source" class="chat-admin-filters__select" @change="emit('update:source', ($event.target as HTMLSelectElement).value)">
        <option value="all">All</option>
        <option value="internal">Internal</option>
        <option value="api">API</option>
        <option value="webhook">Webhook</option>
        <option value="system">System</option>
      </select>
    </div>

    <div class="chat-admin-filters__field">
      <label class="chat-admin-filters__label">Assignment</label>
      <select data-testid="filter-assignment" :value="assignment" class="chat-admin-filters__select" @change="emit('update:assignment', ($event.target as HTMLSelectElement).value as 'all' | 'assigned' | 'unassigned')">
        <option value="all">All</option>
        <option value="assigned">Assigned</option>
        <option value="unassigned">Unassigned</option>
      </select>
    </div>

    <div class="chat-admin-filters__field">
      <label class="chat-admin-filters__label">Restrictions</label>
      <select data-testid="filter-participant-restriction" :value="participantRestriction" class="chat-admin-filters__select" @change="emit('update:participantRestriction', ($event.target as HTMLSelectElement).value as 'all' | 'blocked' | 'restricted')">
        <option value="all">All</option>
        <option value="blocked">Blocked</option>
        <option value="restricted">Restricted</option>
      </select>
    </div>

    <div class="chat-admin-filters__toggles">
      <label class="chat-admin-filters__toggle">
        <input
          data-testid="filter-unread-only"
          type="checkbox"
          :checked="unreadOnly"
          @change="emit('update:unreadOnly', ($event.target as HTMLInputElement).checked)"
        />
        <span>Unread only</span>
      </label>
      <label class="chat-admin-filters__toggle">
        <input
          data-testid="filter-failed-webhook"
          type="checkbox"
          :checked="failedWebhookDeliveryOnly"
          @change="emit('update:failedWebhookDeliveryOnly', ($event.target as HTMLInputElement).checked)"
        />
        <span>Failed webhook delivery</span>
      </label>
      <label class="chat-admin-filters__toggle">
        <input
          data-testid="filter-imported-only"
          type="checkbox"
          :checked="importedOnly"
          @change="emit('update:importedOnly', ($event.target as HTMLInputElement).checked)"
        />
        <span>Imported messages</span>
      </label>
      <button data-testid="filter-reset" type="button" class="chat-admin-filters__reset" @click="emit('reset')">Reset</button>
    </div>
  </section>
</template>

<script setup lang="ts">
defineProps<{
  search: string;
  type: string;
  status: string;
  visibility: string;
  source: string;
  unreadOnly: boolean;
  assignment: 'all' | 'assigned' | 'unassigned';
  participantRestriction: 'all' | 'blocked' | 'restricted';
  failedWebhookDeliveryOnly: boolean;
  importedOnly: boolean;
}>();

const emit = defineEmits<{
  'update:search': [value: string];
  'update:type': [value: string];
  'update:status': [value: string];
  'update:visibility': [value: string];
  'update:source': [value: string];
  'update:unreadOnly': [value: boolean];
  'update:assignment': [value: 'all' | 'assigned' | 'unassigned'];
  'update:participantRestriction': [value: 'all' | 'blocked' | 'restricted'];
  'update:failedWebhookDeliveryOnly': [value: boolean];
  'update:importedOnly': [value: boolean];
  reset: [];
}>();
</script>

<style scoped>
.chat-admin-filters{margin-top:0;display:grid;grid-template-columns:minmax(220px,1.8fr) repeat(6,minmax(130px,1fr));gap:10px;align-items:end}
.chat-admin-filters__label{display:block;margin-bottom:5px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;font-weight:700}
.chat-admin-filters__input,.chat-admin-filters__select{width:100%;height:36px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px;font-size:12px}
.chat-admin-filters__input:focus,.chat-admin-filters__select:focus{outline:none;border-color:rgba(96,165,250,.65);box-shadow:0 0 0 2px rgba(59,130,246,.15)}
.chat-admin-filters__toggles{display:flex;gap:10px;flex-wrap:wrap;align-items:center;grid-column:1 / -1}
.chat-admin-filters__toggle{display:inline-flex;align-items:center;gap:6px;color:#cbd5e1;font-size:12px}
.chat-admin-filters__reset{height:30px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px;font-size:12px}
@media (max-width:1280px){.chat-admin-filters{grid-template-columns:repeat(3,minmax(130px,1fr))}}
@media (max-width:1024px){.chat-admin-filters{grid-template-columns:1fr 1fr}}
@media (max-width:700px){.chat-admin-filters{grid-template-columns:1fr}}
</style>
