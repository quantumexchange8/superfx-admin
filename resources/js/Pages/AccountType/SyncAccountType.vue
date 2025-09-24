<script setup>
import Button from "@/Components/Button.vue";
import {IconRefresh} from "@tabler/icons-vue";
import Dialog from "primevue/dialog";
import {ref, watch} from "vue";
import InputLabel from "@/Components/InputLabel.vue";
import SelectChipGroup from "@/Components/SelectChipGroup.vue";
import Checkbox from "primevue/checkbox";
import Loader from "@/Components/Loader.vue";
import Tag from "primevue/tag";
import {useForm} from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
    tradingPlatforms: Array
})

const visible = ref(false);
const selectedTradingPlatform = ref('');

const openDialog = () => {
    visible.value = true;
}

const form = useForm({
    trading_platform: '',
    account_types: null
})

const accountTypes = ref([]);
const syncedAccountTypes = ref([]);

const selectedAccountTypes = ref([]);
const loadingAccountTypes = ref()

const getPlatformAccountTypes = async () => {
    loadingAccountTypes.value = true;

    try {
        const response = await axios.get(
            `/getPlatformAccountTypes?trading_platform=${selectedTradingPlatform.value}`
        );

        // All groups from API
        accountTypes.value = response.data.fetchedGroups;

        // DB-synced ones
        syncedAccountTypes.value = response.data.syncedAccountTypes;

        // Auto-select the DB existing ones
        selectedAccountTypes.value = accountTypes.value.filter(acc =>
            syncedAccountTypes.value.some(
                synced => synced.account_group === acc.name
            )
        );

    } catch (error) {
        console.error('Error getting account types:', error);
    } finally {
        loadingAccountTypes.value = false;
    }
};

watch(selectedTradingPlatform, () => {
    getPlatformAccountTypes()
})

const submitForm = () => {
    form.trading_platform = selectedTradingPlatform.value;
    form.account_types = selectedAccountTypes.value;

    form.post(route('accountType.syncAccountTypes'), {
        onSuccess: () => {
            closeDialog();

            form.reset();
            selectedTradingPlatform.value = '';
            accountTypes.value = [];
            syncedAccountTypes.value = [];
            selectedAccountTypes.value = [];
        }
    })
}

const closeDialog = () => {
    visible.value = false;
}
</script>

<template>
    <Button
        variant="primary-flat"
        type="button"
        class="w-full md:w-auto"
        @click="openDialog"
        disabled
    >
        <IconRefresh size="20" stroke-width="1.25" color="#FFF" />
        {{ $t('public.synchronise') }}
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.synchronise')"
        class="dialog-xs md:dialog-md"
    >
        <form class="flex flex-col gap-5 items-center self-stretch">
            <div class="flex flex-col gap-1 items-start self-stretch">
                <InputLabel for="platform" :value="$t('public.select_platform')" />
                <SelectChipGroup
                    v-model="selectedTradingPlatform"
                    :items="tradingPlatforms"
                    value-key="slug"
                >
                    <template #option="{ item }">
                        <span class="uppercase">{{ item.slug }}</span>
                    </template>
                </SelectChipGroup>
                <InputError :message="form.errors.trading_platform" />
            </div>

            <!-- Available account types -->
            <div class="flex flex-col gap-1 items-start self-stretch">
                <InputLabel for="account_type" :value="$t('public.select_account_type')" />

                <div
                    v-if="loadingAccountTypes"
                    class="flex justify-center items-center w-full py-10"
                >
                    <Loader />
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full">
                    <div
                        v-for="group in accountTypes"
                        :key="group.name"
                        class="flex items-center gap-2"
                    >
                        <Checkbox
                            v-model="selectedAccountTypes"
                            :inputId="group.name"
                            :value="group"
                        />
                        <label :for="group.name" :title="group.name" class="text-sm truncate text-gray-950">{{ group.name }}</label>
                    </div>

                    <div v-if="accountTypes.length === 0" class="text-gray-950 md:col-span-3">
                        <Tag
                            severity="secondary"
                            :value="$t('public.no_account_type')"
                        />
                    </div>
                </div>

                <InputError :message="form.errors.account_types" />
            </div>

            <div class="flex justify-end items-center gap-5 self-stretch">
                <Button
                    type="button"
                    variant="gray-tonal"
                    class="w-full md:w-[120px]"
                    @click="closeDialog"
                    :disabled="form.processing"
                >
                    {{ $t('public.cancel') }}
                </Button>
                <Button
                    variant="primary-flat"
                    class="w-full md:w-[120px]"
                    @click.prevent="submitForm"
                    :disabled="form.processing"
                >
                    {{ $t('public.confirm') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
