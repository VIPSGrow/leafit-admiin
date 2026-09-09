<?php

namespace App\Controllers\Partner;

class WorkingHoursController extends Partner
{
    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
    }

    public function index()
    {
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }

        setPageInfo(
            $this->data,
            labels('working_hours', 'Working Hours') . ' | ' . labels('provider_panel', 'Provider Panel'),
            'working_hours'
        );

        $partner_timings = !empty(fetch_details('partner_timings', ['partner_id' => $this->userId]))
            ? fetch_details('partner_timings', ['partner_id' => $this->userId])
            : [];

        $provider_shifts_by_day = [];
        if ($this->db->tableExists('provider_shifts')) {
            $shiftRows = $this->db->table('provider_shifts')
                ->where('partner_id', $this->userId)
                ->orderBy('day', 'ASC')
                ->orderBy('shift_number', 'ASC')
                ->get()->getResultArray();
            foreach ($shiftRows as $row) {
                $provider_shifts_by_day[$row['day']][] = $row;
            }
        }

        $this->data['partner_timings'] = array_reverse($partner_timings);
        $this->data['provider_shifts_by_day'] = $provider_shifts_by_day;

        return view('backend/partner/template', $this->data);
    }

    public function update()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $days = [
                0 => 'monday',
                1 => 'tuesday',
                2 => 'wednesday',
                3 => 'thursday',
                4 => 'friday',
                5 => 'saturday',
                6 => 'sunday',
            ];

            $startTimes  = $this->request->getPost('start_time') ?? [];
            $endTimes    = $this->request->getPost('end_time') ?? [];
            $extraShifts = $this->request->getPost('extra_shifts') ?? [];
            $dayFlags    = $this->request->getPost();

            // Validate all shift time pairs before touching DB
            foreach ($days as $i => $day) {
                $allShifts = [];

                $start = trim((string) ($startTimes[$i] ?? ''));
                $end   = trim((string) ($endTimes[$i] ?? ''));
                if ($start !== '' && $end !== '') {
                    if ($end <= $start) {
                        return ErrorResponse(labels('shift_end_after_start', 'End time must be after start time'), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $allShifts[] = ['start' => $start, 'end' => $end, 'num' => 1];
                }

                $dayExtras   = $extraShifts[$day] ?? [];
                $extraStarts = $dayExtras['start'] ?? [];
                $extraEnds   = $dayExtras['end'] ?? [];
                $count       = min(count($extraStarts), count($extraEnds));
                for ($j = 0; $j < $count; $j++) {
                    $es = trim((string) $extraStarts[$j]);
                    $ee = trim((string) $extraEnds[$j]);
                    if ($es !== '' && $ee !== '') {
                        if ($ee <= $es) {
                            return ErrorResponse(labels('shift_end_after_start', 'End time must be after start time'), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $allShifts[] = ['start' => $es, 'end' => $ee, 'num' => $j + 2];
                    }
                }

                $shiftCount = count($allShifts);
                for ($a = 0; $a < $shiftCount; $a++) {
                    for ($b = $a + 1; $b < $shiftCount; $b++) {
                        if ($allShifts[$a]['start'] < $allShifts[$b]['end'] && $allShifts[$b]['start'] < $allShifts[$a]['end']) {
                            $msg = str_replace(
                                ['{shift1}', '{shift2}', '{day}'],
                                [$allShifts[$a]['num'], $allShifts[$b]['num'], ucfirst($day)],
                                labels('shift_overlap', 'Shift {shift1} overlaps with Shift {shift2} on {day}')
                            );
                            return ErrorResponse($msg, true, [], [], 200, csrf_token(), csrf_hash());
                        }
                    }
                }
            }

            $this->db->table('provider_shifts')->delete(['partner_id' => $this->userId]);

            foreach ($days as $i => $day) {
                $isOpen = isset($dayFlags[$day]) ? 1 : 0;
                $shiftNumber = 1;

                insert_details([
                    'partner_id'   => $this->userId,
                    'day'          => $day,
                    'shift_number' => $shiftNumber,
                    'opening_time' => $startTimes[$i] ?? '',
                    'closing_time' => $endTimes[$i] ?? '',
                    'is_open'      => $isOpen,
                ], 'provider_shifts');

                $dayExtras   = $extraShifts[$day] ?? [];
                $extraStarts = $dayExtras['start'] ?? [];
                $extraEnds   = $dayExtras['end'] ?? [];
                $count       = min(count($extraStarts), count($extraEnds));

                for ($j = 0; $j < $count; $j++) {
                    $start = trim((string) $extraStarts[$j]);
                    $end   = trim((string) $extraEnds[$j]);
                    if ($start === '' || $end === '') {
                        continue;
                    }
                    $shiftNumber++;
                    insert_details([
                        'partner_id'   => $this->userId,
                        'day'          => $day,
                        'shift_number' => $shiftNumber,
                        'opening_time' => $start,
                        'closing_time' => $end,
                        'is_open'      => $isOpen,
                    ], 'provider_shifts');
                }
            }

            return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> app/Controllers/partner/WorkingHoursController.php - update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
