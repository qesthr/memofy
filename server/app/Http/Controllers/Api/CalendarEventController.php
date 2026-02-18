<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CalendarEvent;

class CalendarEventController extends Controller
{
    /**
     * Display a listing of calendar events with pagination.
     * 
     * PERFORMANCE: Previously fetched ALL events without pagination.
     * Now uses paginate() for efficient data retrieval.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $page = $request->get('page', 1);
        
        try {
            $events = CalendarEvent::orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Format events to match FullCalendar/Frontend expectation
            $formatted = $events->map(function($ev) {
                return [
                    'id' => $ev->id,
                    'title' => $ev->title,
                    'start' => $ev->start_date ?? $ev->start,
                    'end' => $ev->end_date ?? $ev->end,
                    'color' => $this->getEventColor($ev->category),
                    'allDay' => $ev->all_day ?? false,
                    'description' => $ev->description ?? '',
                    'category' => $ev->category,
                    'status' => $ev->status,
                ];
            });
            
            return response()->json([
                'data' => $formatted,
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ]
            ]);
        }
    }

    /**
     * Get event color based on category.
     */
    protected function getEventColor($category)
    {
        $colors = [
            'urgent' => '#EF4444',
            'high' => '#F97316',
            'meeting' => '#3B82F6',
            'deadline' => '#EF4444',
            'reminder' => '#8B5CF6',
            'standard' => '#10B981',
            'low' => '#6B7280',
        ];
        
        return $colors[$category] ?? '#3B82F6';
    }
}
