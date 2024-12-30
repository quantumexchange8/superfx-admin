    <script setup>
    import Button from "@/Components/Button.vue";
    import {onMounted, ref} from "vue";
    import InputText from 'primevue/inputtext';
    import InputError from '@/Components/InputError.vue';
    import InputLabel from '@/Components/InputLabel.vue';
    import { useForm } from "@inertiajs/vue3";
    import ColorPicker from 'primevue/colorpicker';
    import Dropdown from 'primevue/dropdown';
    import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";

    const props = defineProps({
        group: Object,
    })

    const emit = defineEmits(['closeDialog']);

    const name = ref(props.group.name);
    const chargesPercent = ref(props.group.fee_charges);
    const color = ref(props.group.color);
    const memberCount = ref(props.group.member_count);
    const currentLeader = ref({value: props.group.user_id, name: props.group.leader_name, profile_photo: props.group.profile_photo});
    const agents = ref([]);

    const getAgents = async () => {
        try {
            const agentResponse = await axios.get('/group/getAgents');
            agents.value = agentResponse.data;
            agents.value.push(currentLeader.value);
        } catch (error) {
            console.error('Error fetching agents:', error);
        }
    };

    onMounted(() => {
        getAgents();
    })

    const form = useForm({
        group_name: '',
        fee_charges: '',
        color: '',
        agent: currentLeader.value,
        group_members: null,
    })

    const submitForm = () => {
        form.group_name = name.value;
        form.fee_charges = chargesPercent.value;
        form.color = color.value;
        form.group_members = memberCount.value;

        form.patch(route('group.edit', props.group.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                emit('closeDialog', false);
            },
            onError: (e) => {
                console.log('err', e);
            }
        })
    }

    </script>

    <template>
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
                                v-model="name"
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
                                v-model="chargesPercent"
                                placeholder="0.00%"
                                :invalid="!!form.errors.fee_charges"
                            />
                            <InputError :message="form.errors.fee_charges" />
                        </div>
                        <div class="flex flex-col items-start gap-1 self-stretch md:col-span-2">
                            <InputLabel for="color" :value="$t('public.color')" :invalid="!!form.errors.color" />

                            <ColorPicker v-model="color" id="color"/>

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
                                v-model="memberCount"
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
                    {{ $t('public.save') }}
                </Button>
            </div>
        </form>
    </template>