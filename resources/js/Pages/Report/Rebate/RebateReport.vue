<script setup>
import { ref, watch } from "vue";
import axios from 'axios';
import RebateSummary from "@/Pages/Report/Rebate/Partials/RebateSummary.vue";
import RebateSummaryChart from "@/Pages/Report/Rebate/Partials/RebateSummaryChart.vue";
import RebateListingTable from "@/Pages/Report/Rebate/Partials/RebateListingTable.vue";
import { usePage } from "@inertiajs/vue3";
import { transactionFormat } from '@/Composables/index.js';

const { formatDate, formatDateTime, formatAmount } = transactionFormat();

// Initialize the form with user data
const user = usePage().props.auth.user;

// Define reactive variables
const rebateSummary = ref([]);
const totalVolume = ref(0);
const totalRebate = ref(0);
const dateRange = ref([]);

// Function to fetch rebate summary data
const getResults = async (dateRange) => {
    const [startDate, endDate] = dateRange;
    let url = `/report/getRebateSummary`;

    // Append date range to the URL if it's not null
    if (startDate && endDate) {
        url += `?startDate=${formatDate(startDate)}&endDate=${formatDate(endDate)}`;
    }

    try {
        const response = await axios.get(url);
        const data = response.data;

        // Update the rebateSummary and totals
        rebateSummary.value = data.rebateSummary;
        totalVolume.value = data.totalVolume;
        totalRebate.value = data.totalRebate;
    } catch (error) {
        console.error('Error fetching rebate summary:', error);
    }
};

// Watch for changes in the dateRange and fetch rebate summary data
watch(dateRange, (newDateRange) => {
    if (newDateRange === null || newDateRange === undefined) {
        // Handle null or undefined newDateRange
        getResults([]);
    } else {
        getResults(newDateRange);
    }
});

// Handle the update-date event from RebateListingTable
const handleUpdateDate = (newDateRange) => {
    // console.log('Date Range Received:', newDateRange);
    dateRange.value = newDateRange;
};

</script>

<template>
    <div class="w-full grid grid-cols-1 xl:grid-cols-2 gap-5">
        <RebateSummary :rebateSummary="rebateSummary" :totalVolume="totalVolume" :totalRebate="totalRebate" />
        <RebateSummaryChart :rebateSummary="rebateSummary" :totalVolume="totalVolume" :totalRebate="totalRebate" />
    </div>
    <RebateListingTable @update-date="handleUpdateDate" />
</template>
