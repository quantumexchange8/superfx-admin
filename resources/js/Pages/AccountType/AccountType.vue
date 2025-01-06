<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/Button.vue';
import { IconRefresh } from '@tabler/icons-vue';
import DataTable from 'primevue/datatable';
import { onMounted, ref, watchEffect } from 'vue';
import Column from 'primevue/column';
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import { usePage } from '@inertiajs/vue3';
import AccountTypeAction from '@/Pages/AccountType/Partials/AccountTypeAction.vue';
import Dialog from 'primevue/dialog';

const props = defineProps({
    leverages: Array,
})

const accountTypes = ref();
const loading = ref(false);

const getAccountTypes = async () => {
    loading.value = true;

    try {
        const response = await axios.get('/account_type/getAccountTypes');
        accountTypes.value = response.data.accountTypes;
    } catch (error) {
        console.error('Error getting account types:', error);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    getAccountTypes();
})

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getAccountTypes();
    }
});

const visibleDetails = ref(false);
const selected_row = ref();
const rowClicked = (data) => {
    if (window.innerWidth < 768) {
        visibleDetails.value = true;
        selected_row.value = data;
    }
}
</script>

<template>
    <AuthenticatedLayout :title="$t('public.account_type')">
        <div class="flex flex-col items-center gap-5 md:gap-8">
            <div class="flex justify-end items-center self-stretch">
                <Button
                    variant="primary-flat"
                    type="button"
                    class="w-full md:w-auto"
                    :href="route('accountType.syncAccountTypes')"
                >
                    <IconRefresh size="20" stroke-width="1.25" color="#FFF" />
                    {{ $t('public.synchronise') }}
                </Button>
            </div>

            <div
                class="py-6 px-4 md:p-6 flex flex-col justify-center items-center gap-6 self-stretch rounded-2xl border border-solid border-gray-200 bg-white shadow-table">
                <DataTable
                    :value="accountTypes"
                    removableSort
                    :loading="loading"
                    @row-click="rowClicked($event.data)"
                >
                    <template #empty>
                        <Empty :title="$t('public.no_account_type_header')"
                               :message="$t('public.no_account_type_caption')"/>
                    </template>

                    <template #loading>
                        <div class="flex flex-col gap-2 items-center justify-center">
                            <Loader />
                            <span class="text-sm text-gray-700">{{ $t('public.loading_account_type') }}</span>
                        </div>
                    </template>

                    <template v-if="accountTypes?.length > 0">
                        <Column field="name" sortable class="w-1/2 md:w-1/4">
                            <template #header>
                                <span class="w-10 truncate sm:w-auto">{{ $t('public.name') }}</span>
                            </template>
                            <template #body="slotProps">
                                <span class="w-20 truncate inline-block sm:w-auto">{{ slotProps.data.name }}</span>
                            </template>
                        </Column>
                        <Column field="max_acc" style="width: 20%" class="hidden md:table-cell">
                            <template #header>
                                <span>{{ $t('public.max_account') }}</span>
                            </template>
                            <template #body="slotProps">
                                {{ slotProps.data.maximum_account_number }}
                            </template>
                        </Column>
                        <Column field="trade_delay" style="width: 20%" class="hidden md:table-cell">
                            <template #header>
                                <span>{{ $t('public.trade_delay') }}</span>
                            </template>
                            <template #body="slotProps">
                                {{ slotProps.data.trade_delay }}
                            </template>
                        </Column>
                        <Column field="total_acc" sortable class="w-1/3 md:w-1/5" bodyClass="text-center md:text-left">
                            <template #header>
                                <span class="w-14 truncate sm:w-auto">{{ $t('public.total_account') }}</span>
                            </template>
                            <template #body="slotProps">
                                {{ slotProps.data.total_account }}
                            </template>
                        </Column>
                        <Column field="action" style="width: 15%">
                            <template #body="slotProps">
                                <div class="py-2 px-3 flex justify-center items-center gap-2 flex-1">
                                    <AccountTypeAction 
                                        :accountType="slotProps.data" 
                                        :leverages="props.leverages" 
                                        :loading="loading"
                                    />
                                </div>
                            </template>
                        </Column>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
