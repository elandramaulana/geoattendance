<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Office;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pastikan ada data employee dan office
        $employee = Employee::first();
        $office = Office::first();

        if (!$employee || !$office) {
            $this->command->warn('Please make sure you have at least one Employee and Office record before running this seeder.');
            return;
        }

        // Data attendance sample
        $attendanceData = [
            [
                'employee_id' => $employee->id,
                'office_id' => $office->id,
                'date' => Carbon::now()->subDays(4)->format('Y-m-d'), // 4 hari yang lalu
                'clock_in' => '08:00:00',
                'clock_out' => '17:00:00',
                'clock_in_lat' => -6.2088,
                'clock_in_lng' => 106.8456,
                'clock_out_lat' => -6.2088,
                'clock_out_lng' => 106.8456,
                'clock_in_address' => 'Jl. Sudirman No.1, Jakarta Pusat',
                'clock_out_address' => 'Jl. Sudirman No.1, Jakarta Pusat',
                'work_duration' => 480, // 8 jam = 480 menit (sudah dikurangi break 1 jam)
                'overtime_duration' => 0,
                'status' => 'present',
                'notes' => 'Hari kerja normal',
            ],
            [
                'employee_id' => $employee->id,
                'office_id' => $office->id,
                'date' => Carbon::now()->subDays(3)->format('Y-m-d'), // 3 hari yang lalu
                'clock_in' => '08:15:00',
                'clock_out' => '17:30:00',
                'clock_in_lat' => -6.2088,
                'clock_in_lng' => 106.8456,
                'clock_out_lat' => -6.2088,
                'clock_out_lng' => 106.8456,
                'clock_in_address' => 'Jl. Sudirman No.1, Jakarta Pusat',
                'clock_out_address' => 'Jl. Sudirman No.1, Jakarta Pusat',
                'work_duration' => 495, // 8 jam 15 menit = 495 menit (sudah dikurangi break 1 jam)
                'overtime_duration' => 15, // 15 menit overtime
                'status' => 'late',
                'notes' => 'Terlambat 15 menit karena macet',
            ],
            [
                'employee_id' => $employee->id,
                'office_id' => $office->id,
                'date' => Carbon::now()->subDays(2)->format('Y-m-d'), // 2 hari yang lalu
                'clock_in' => null,
                'clock_out' => null,
                'clock_in_lat' => null,
                'clock_in_lng' => null,
                'clock_out_lat' => null,
                'clock_out_lng' => null,
                'clock_in_address' => null,
                'clock_out_address' => null,
                'work_duration' => 0,
                'overtime_duration' => 0,
                'status' => 'absent',
                'notes' => 'Sakit demam',
            ],
            [
                'employee_id' => $employee->id,
                'office_id' => $office->id,
                'date' => Carbon::now()->subDays(1)->format('Y-m-d'), // Kemarin
                'clock_in' => '07:45:00',
                'clock_out' => '18:30:00',
                'clock_in_lat' => -6.2088,
                'clock_in_lng' => 106.8456,
                'clock_out_lat' => -6.2088,
                'clock_out_lng' => 106.8456,
                'clock_in_address' => 'Jl. Sudirman No.1, Jakarta Pusat',
                'clock_out_address' => 'Jl. Sudirman No.1, Jakarta Pusat',
                'work_duration' => 585, // 9 jam 45 menit = 585 menit (sudah dikurangi break 1 jam)
                'overtime_duration' => 105, // 1 jam 45 menit overtime
                'status' => 'present',
                'notes' => 'Lembur untuk menyelesaikan project',
            ],
            [
                'employee_id' => $employee->id,
                'office_id' => $office->id,
                'date' => Carbon::now()->format('Y-m-d'), // Hari ini
                'clock_in' => '08:05:00',
                'clock_out' => null, // Belum clock out
                'clock_in_lat' => -6.2088,
                'clock_in_lng' => 106.8456,
                'clock_out_lat' => null,
                'clock_out_lng' => null,
                'clock_in_address' => 'Jl. Sudirman No.1, Jakarta Pusat',
                'clock_out_address' => null,
                'work_duration' => null, // Belum dihitung karena belum clock out
                'overtime_duration' => null,
                'status' => 'late',
                'notes' => 'Terlambat 5 menit',
            ],
        ];

        // Insert data
        foreach ($attendanceData as $data) {
            Attendance::create($data);
        }

        $this->command->info('Attendance seeder completed successfully! Created 5 attendance records.');
    }
}