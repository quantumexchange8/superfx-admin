<script setup>
import Button from "@/Components/Button.vue";
import Dialog from 'primevue/dialog';
import {onUpdated, ref} from "vue";
import { IconPlus } from '@tabler/icons-vue';
import InputText from 'primevue/inputtext';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { useForm } from "@inertiajs/vue3";
import ColorPicker from 'primevue/colorpicker';
import Dropdown from 'primevue/dropdown';
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";

const visible = ref(false);
const color = ref('ff0000');
const agents = ref();
const getAgents = async () => {
    try {
        const agentResponse = await axios.get('/group/getAgents');
        agents.value = agentResponse.data;
    } catch (error) {
        console.error('Error fetching agents:', error);
    }
};

onUpdated(() => {
    getAgents();
})

const form = useForm({
    group_name: '',
    fee_charges: '',
    color: '',
    agent: '',
    group_members: null,
})

const submitForm = () => {
    form.color = color.value;

    form.post(route('group.create'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            visible.value = false;
        },
        onError: () => {
            console.log('err');
        }
    })
}

</script>

<template>
    <Button
        variant="primary-flat"
        type="button"
        size="base"
        class="w-full md:w-auto"
        v-slot="{ iconSizeClasses }"
        @click="visible = true"
    >
        <div class="flex justify-center items-center gap-3 self-stretch">
            <IconPlus size="20" stroke-width="1.25" color="white" />
            <div class="text-white text-center text-sm font-medium">
                {{ $t('public.new_group') }}
            </div>
        </div>
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        header="New Group"
        class="dialog-xs sm:dialog-md"
    >
        <form @submit.prevent="submitForm()">
            <div class="flex flex-col items-center gap-8 self-stretch">
                <div class="flex flex-col items-center gap-3 self-stretch">
                    <div class="self-stretch text-gray-950 text-sm font-bold">
                        {{ $t('public.group_information') }}
                    </div>
                    <div class="grid items-center gap-3 self-stretch grid-cols-1 md:grid-cols-2 md:gap-5">
                        <div class="flex flex-col items-start gap-1 self-stretch">
                            <InputLabel for="groupName" :value="$t('public.group_name')" :invalid="!!form.errors.group_name" />
                            <InputText
                                id="groupName"
                                type="text"
                                class="block w-full"
                                v-model="form.group_name"
                                :placeholder="$t('public.group_name_placeholder')"
                                :invalid="!!form.errors.group_name"
                            />
                            <InputError :message="form.errors.group_name" />
                        </div>
                        <div class="flex flex-col items-start gap-1 self-stretch">
                            <InputLabel for="feeCharges" :value="$t('public.fee_charges')" :invalid="!!form.errors.fee_charges" />
                            <InputText
                                id="feeCharges"
                                type="number"
                                class="block w-full"
                                v-model="form.fee_charges"
                                placeholder="0.00%"
                                :invalid="!!form.errors.fee_charges"
                            />
                            <InputError :message="form.errors.fee_charges" />
                        </div>
                        <div class="flex flex-col items-start gap-1 self-stretch md:col-span-2">
                            <InputLabel for="color" :value="$t('public.color')" :invalid="!!form.errors.color" />

                            <ColorPicker v-model="color" id="Color"/>

                            <InputError :message="form.errors.color" />
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-3 self-stretch">
                    <div class="self-stretch text-gray-950 text-sm font-bold">
                        {{ $t('public.agent') }}
                    </div>
                    <div class="flex flex-col items-start gap-3 self-stretch md:flex-row md:justify-center md:content-start md:gap-5 md:flex-wrap">
                        <div class="flex flex-col items-start gap-1 self-stretch md:flex-1">
                            <InputLabel for="agent" :value="$t('public.agent')" :invalid="!!form.errors.agent" />
                            <Dropdown
                                id="agent"
                                v-model="form.agent"
                                :options="agents"
                                filter
                                :filterFields="['name', 'phone_code']"
                                optionLabel="name"
                                :placeholder="$t('public.select_agent_placeholder')"
                                class="w-full"
                                scroll-height="236px"
                                :invalid="!!form.errors.agent"
                            >
                                <template #value="slotProps">
                                    <div v-if="slotProps.value" class="flex items-center gap-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 rounded-full overflow-hidden">
                                                <template v-if="slotProps.value.profile_photo">
                                                    <img :src="slotProps.value.profile_photo" alt="profile_picture" />
                                                </template>
                                                <template v-else>
                                                    <DefaultProfilePhoto />
                                                </template>
                                            </div>
                                            <div>{{ slotProps.value.name }}</div>
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-400">
                                            {{ slotProps.placeholder }}
                                    </span>
                                </template>
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded-full overflow-hidden">
                                            <template v-if="slotProps.option.profile_photo">
                                                <img :src="slotProps.option.profile_photo" alt="profile_picture" />
                                            </template>
                                            <template v-else>
                                                <DefaultProfilePhoto />
                                            </template>
                                        </div>
                                        <div>{{ slotProps.option.name }}</div>
                                    </div>
                                </template>
                            </Dropdown>
                            <InputError :message="form.errors.agent" />
                        </div>
                        <div class="flex flex-col items-start gap-1 self-stretch md:flex-1">
                            <InputLabel for="groupMembers" :value="$t('public.total_group_members')" :invalid="!!form.errors.group_members" />
                            <InputText
                                id="groupMembers"
                                type="number"
                                class="block w-full"
                                v-model="form.group_members"
                                placeholder="0"
                                :invalid="!!form.errors.group_members"
                                disabled
                            />
                            <InputError :message="form.errors.group_members" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="pt-5 flex flex-col items-end self-stretch">
                <Button
                    variant="primary-flat"
                    size="base"
                    :disabled="form.processing"
                >
                    {{ $t('public.create') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>