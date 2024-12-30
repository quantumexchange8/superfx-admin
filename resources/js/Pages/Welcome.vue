<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Column from 'primevue/column';
import Button from '@/Components/Button.vue';
import { ref, onMounted } from 'vue';
import { FilterMatchMode } from 'primevue/api';
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import Dropdown from "primevue/dropdown";

onMounted(() => {
    getResults();
})
const getResults = async (langVal) => {
    try {
        const response = await axios.get('http://superfx-admin.test/getData');
        customers.value = response.data.users;
        countries.value = response.data.countries;

    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

const dt = ref();
const exportCSV = () => {
    dt.value.exportCSV();
};

const customers = ref();
const countries = ref();

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
    'country.name': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
    representative: { value: null, matchMode: FilterMatchMode.IN },
    status: { value: null, matchMode: FilterMatchMode.EQUALS },
    verified: { value: null, matchMode: FilterMatchMode.EQUALS }
});

const selectedCity = ref();
</script>

<template>
    <AuthenticatedLayout title="Component">
        <div class="p-6 flex flex-col items-center justify-center self-stretch gap-6 border border-gray-200 bg-white shadow-table rounded-2xl">
            <div>
                <Dropdown v-model="selectedCity" editable :options="countries" optionLabel="name" optionValue="phone_code" placeholder="Select a City" class="w-full md:w-[14rem]">
                    <template #option="slotProps">
                        <div class="flex items-center">
                            <div>{{ slotProps.option.name }} <span class="text-gray-500">{{ slotProps.option.phone_code }}</span></div>
                        </div>
                    </template>
                </Dropdown>
            </div>
            <DataTable
                v-model:filters="filters"
                :value="customers"
                paginator
                removableSort
                :rows="10"
                :rowsPerPageOptions="[10, 20, 50, 100]"
                tableStyle="min-width: 50rem"
                paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords} entries"
                :globalFilterFields="['first_name']"
                ref="dt"
            >
                <template #header>
                    <div class="flex justify-between items-center self-stretch">
                        <div>
                            <InputText v-model="filters['global'].value" placeholder="Keyword Search" class="font-normal" />
                        </div>
                        <div >
                            <Button variant="primary-outlined" @click="exportCSV($event)">
                                Export
                            </Button>
                        </div>
                    </div>
                </template>
                <template #empty> No customers found. </template>
                <template #loading> Loading customers data. Please wait. </template>
                <Column field="id" sortable header="Id" style="width: 25%">
                    <template #body="slotProps">
                        MID00000{{ slotProps.data.id }}
                    </template>
                </Column>
                <Column field="first_name" sortable header="Name" style="width: 25%">
                    <template #body="slotProps">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full overflow-hidden">
                                <DefaultProfilePhoto />
                            </div>
                            <div class="flex flex-col items-start">
                                <div class="font-medium">
                                    {{ slotProps.data.first_name }}
                                </div>
                                <div class="text-gray-500 text-xs">
                                    {{ slotProps.data.email }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Column>
                <Column field="company" header="Company" style="width: 25%"></Column>
                <Column field="representative.name" header="Representative" style="width: 25%"></Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
