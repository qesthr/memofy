<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// use App\Models\CalendarEvent; // Assuming model exists or using DB facade for now

class CalendarEventController extends Controller
{
    public function index(Request $request)
    {
        // If Model exists, use it. checking existence via simple query first.
        // Assuming table 'calendar_events' exists from migration.
        
        try {
            $events = DB::table('calendar_events')->get();
            
            // Format events to match FullCalendar/Frontend expectation
            // Frontend expects: title, start, end, color, allDay?
            
            $formatted = $events->map(function($ev) {
                return [
                    'id' => $ev->id,
                    'title' => $ev->title,
                    'start' => $ev->start_date, // Adjust based on migration schema
                    'end' => $ev->end_date,
                    'color' => '#3B82F6', // App Blue
                    'allDay' => false, // Adjust if schema has it
                    'description' => $ev->description ?? ''
                ];
            });
            
            return response()->json($formatted);
        } catch (\Exception $e) {
            // Table might not exist or empty
            return response()->json([]);
        }
    }
}
