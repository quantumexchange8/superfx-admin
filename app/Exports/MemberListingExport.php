<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MemberListingExport implements FromCollection, WithHeadings
{
    protected $members;

    public function __construct($members)
    {
        $this->members = $members;
    }

    public function collection()
    {
        // Fetch the records with proper sorting at the database level
        $records = $this->members->with(['upline:id,name', 'groupHasUser.group:id,name'])
            ->select([
                'id', // Include the 'id' field for sorting purposes
                'name',
                'email',
                'id_number',
                'upline_id',
                'created_at',
            ])
            ->orderByDesc('id') // Sorting at the database level
            ->get();

        $result = [];

        // Using foreach to iterate through the sorted records
        foreach ($records as $record) {
            // Add the data to the result but include 'id' for sorting purposes
            $result[] = [
                'id' => $record->id,  // Keep the 'id' for sorting
                'Name' => $record->name,
                'Email' => $record->email,
                'Id Number' => $record->id_number,
                'Group' => $record->groupHasUser->group->name ?? '',
                'Upline' => $record->upline->name ?? '',
                'Joined Date' => $record->created_at ? $record->created_at->format('Y-m-d') : '',
            ];
        }

        // Sort by 'id' and then remove the 'id' column from the result
        $sortedResults = collect($result)->sortByDesc('id')->values();

        // Remove the 'id' column after sorting
        $finalResults = $sortedResults->map(function ($item) {
            unset($item['id']);  // Remove the 'id' field
            return $item;
        });

        return $finalResults;
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Id Number', 'Group', 'Upline', 'Joined Date'];
    }
}
