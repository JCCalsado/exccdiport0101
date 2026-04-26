<template>
    <div class="workflows-index">
        <div class="header">
            <h1>Workflows</h1>
            <button @click="showCreateModal = true" class="btn-primary">Create Workflow</button>
        </div>

        <div class="filters">
            <input v-model="filters.search" type="text" placeholder="Search workflows..." @input="search" />
            <select v-model="filters.type" @change="search">
                <option value="">All Types</option>
                <option value="student">Student</option>
                <option value="accounting">Accounting</option>
                <option value="general">General</option>
            </select>
        </div>

        <div class="workflows-grid">
            <div v-for="workflow in workflows.data" :key="workflow.id" class="workflow-card">
                <div class="workflow-header">
                    <h3>{{ workflow.name }}</h3>
                    <span :class="['badge', `badge-${workflow.type}`]">
                        {{ workflow.type }}
                    </span>
                </div>
                <p class="workflow-description">{{ workflow.description }}</p>
                <div class="workflow-meta">
                    <span>{{ workflow.steps.length }} steps</span>
                    <span :class="workflow.is_active ? 'active' : 'inactive'">
                        {{ workflow.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="workflow-actions">
                    <Link :href="`/workflows/${workflow.id}`" class="btn-secondary"> View Details </Link>
                </div>
            </div>
        </div>

        <Pagination :links="workflows.links" />
    </div>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    workflows: Object,
    filters: Object,
});

const showCreateModal = ref(false);
const filters = ref({ ...props.filters });

const search = () => {
    router.get('/workflows', filters.value, {
        preserveState: true,
        replace: true,
    });
};
</script>

<style scoped>
.workflows-index {
    padding: 2rem;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.filters {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.workflows-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.workflow-card {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1.5rem;
    background: white;
}

.workflow-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.badge-student {
    background: #dbeafe;
    color: #1e40af;
}
.badge-accounting {
    background: #fef3c7;
    color: #92400e;
}
.badge-general {
    background: #e5e7eb;
    color: #374151;
}
</style>
