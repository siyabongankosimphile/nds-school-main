(function($){
  'use strict';
  $(document).ready(function(){
    var el = document.getElementById('nds-frontend-calendar');
    if (!el || typeof window.FullCalendar === 'undefined') {
      console.error('FullCalendar not loaded or calendar element not found');
      return;
    }

    var calendar;
    var calendarConfig = {
      initialView: 'dayGridMonth',
      initialDate: new Date(), // Set to today
      nowIndicator: true, // Show indicator for current time
      headerToolbar: {
        left: 'prev,next',
        center: 'title',
        right: 'timeGridDay,timeGridWeek,dayGridMonth,dayGridYear'
      },
      views: {
        timeGridDay: {
          titleFormat: { year: 'numeric', month: 'long', day: 'numeric' },
          slotMinTime: '06:00:00',
          slotMaxTime: '22:00:00'
        },
        dayGridMonth: {
          titleFormat: { year: 'numeric', month: 'long' },
          dayMaxEvents: true
        },
        timeGridWeek: {
          titleFormat: { year: 'numeric', month: 'long', day: 'numeric' },
          slotMinTime: '06:00:00',
          slotMaxTime: '22:00:00',
          slotDuration: '00:30:00', // 30-minute slots for better granularity
          slotLabelInterval: '01:00:00', // Show labels every hour
          allDaySlot: true,
          allDayText: 'All Day',
          eventMaxStack: 3, // Limit vertical stacking to 3 events
          eventMinHeight: 30, // Minimum height for events (pixels)
          eventShortHeight: 30, // Height for short events
          expandRows: true, // Expand rows to fit content
          scrollTime: '08:00:00', // Start scroll position at 8am
          scrollTimeReset: false // Don't reset scroll on navigation
        },
        dayGridYear: {
          titleFormat: { year: 'numeric' },
          dayMaxEvents: 3,
          moreLinkClick: 'popover',
          fixedWeekCount: false,
          showNonCurrentDates: false
        }
      },
      height: 'auto',
      firstDay: 1, // Start week on Monday
      editable: false,
      selectable: false,
      dayMaxEvents: true,
      moreLinkClick: 'popover',
      eventInteractive: true,
      events: function(info, successCallback, failureCallback) {
        // Safely get view type
        var viewType = (info && info.view && info.view.type) ? info.view.type : 'dayGridMonth';
        
        $.ajax({
          url: ndsFrontendCalendar.ajaxurl,
          type: 'POST',
          data: {
            action: 'nds_public_calendar_events',
            nonce: ndsFrontendCalendar.nonce,
            start: info.startStr,
            end: info.endStr,
            view: viewType // Pass view type to filter events
          },
          success: function(response) {
            if (response && response.success) {
              successCallback(response.data || []);
            } else {
              console.error('Calendar events failed:', response);
              failureCallback('Failed to load events');
            }
          },
          error: function() {
            failureCallback('Network error');
          }
        });
      },
      eventClick: function(info) {
        info.jsEvent.preventDefault(); // Prevent default behavior
        
        var event = info.event;
        var props = event.extendedProps || {};
        var type = props.type || 'event';
        
        console.log('Frontend event clicked:', event.title, event.id);
        
        var title = event.title;
        var props = event.extendedProps || {};
        var type = props.type || 'event';
        
        var title = event.title;
        var content = '<div class="space-y-4">';
        
        // Event title
        content += '<div>';
        content += '<h3 class="text-lg font-semibold text-gray-900 mb-2">' + title + '</h3>';
        content += '</div>';
        
        // Description
        if (props.description) {
          content += '<div>';
          content += '<label class="block text-sm font-medium text-gray-700 mb-1">Description</label>';
          content += '<p class="text-gray-600 bg-gray-50 p-3 rounded-lg">' + props.description + '</p>';
          content += '</div>';
        }
        
        // Location
        if (props.location) {
          content += '<div>';
          content += '<label class="block text-sm font-medium text-gray-700 mb-1">Location</label>';
          content += '<p class="text-gray-800"><i class="fas fa-map-marker-alt mr-2 text-gray-500"></i>' + props.location + '</p>';
          content += '</div>';
        }
        
        // Lecturer (for schedules)
        if (type === 'schedule' && props.lecturer) {
          content += '<div>';
          content += '<label class="block text-sm font-medium text-gray-700 mb-1">Lecturer</label>';
          content += '<p class="text-gray-800"><i class="fas fa-user mr-2 text-gray-500"></i>' + props.lecturer + '</p>';
          content += '</div>';
        }
        
        // Program info
        if (type === 'program' && props.program_id) {
          content += '<div>';
          content += '<label class="block text-sm font-medium text-gray-700 mb-1">Type</label>';
          content += '<p class="text-gray-800"><i class="fas fa-graduation-cap mr-2 text-gray-500"></i>Active Program</p>';
          content += '</div>';
        }
        
        // Date/Time
        content += '<div>';
        content += '<label class="block text-sm font-medium text-gray-700 mb-1">Date & Time</label>';
        content += '<div class="bg-gray-50 p-3 rounded-lg">';
        content += '<p class="text-gray-800 mb-1"><i class="fas fa-clock mr-2 text-gray-500"></i><strong>Start:</strong> ' + event.start.toLocaleString() + '</p>';
        if (event.end) {
          content += '<p class="text-gray-800"><i class="fas fa-clock mr-2 text-gray-500"></i><strong>End:</strong> ' + event.end.toLocaleString() + '</p>';
        }
        content += '</div>';
        content += '</div>';
        
        content += '</div>';
        
        // Show modal
        console.log('Showing frontend modal for:', title);
        var modal = document.getElementById('nds-event-modal');
        var titleEl = document.getElementById('event-modal-title');
        var contentEl = document.getElementById('event-modal-content');
        
        console.log('Frontend modal elements found:', !!modal, !!titleEl, !!contentEl);
        
        if (modal && titleEl && contentEl) {
            titleEl.textContent = title;
            contentEl.innerHTML = content;
            modal.classList.remove('hidden');
            console.log('Frontend modal shown successfully');
        } else {
            console.error('Frontend modal elements not found');
            alert('Event: ' + title + '\n\n' + content.replace(/<[^>]*>/g, ''));
        }
      },
      eventDisplay: 'block',
      eventTimeFormat: {
        hour: 'numeric',
        minute: '2-digit',
        meridiem: 'short'
      },
      eventOverlap: true, // Allow events to overlap (they'll stack)
      eventConstraint: false, // Don't constrain events to business hours
      eventMinHeight: 25, // Minimum event height in pixels
      eventShortHeight: 30, // Height for short events
      eventMaxStack: 4, // Maximum number of stacked events before showing "+X more"
      eventOrder: 'start,-duration', // Sort by start time, then by duration (shortest first)
      eventOrderStrict: false, // Allow flexible ordering
      // Better event rendering
      eventDidMount: function(info) {
        // Add custom styling for better readability
        var eventEl = info.el;
        var event = info.event;
        
        // Truncate long titles
        var title = event.title;
        if (title.length > 40) {
          title = title.substring(0, 37) + '...';
          eventEl.querySelector('.fc-event-title').textContent = title;
          eventEl.setAttribute('title', event.title); // Full title on hover
        }
        
        // Add better contrast for overlapping events
        if (eventEl.classList.contains('fc-event-start')) {
          eventEl.style.borderLeft = '3px solid ' + (event.backgroundColor || '#8b5cf6');
        }
        
        // Improve text readability
        eventEl.style.fontSize = '12px';
        eventEl.style.lineHeight = '1.3';
        eventEl.style.padding = '2px 4px';
      }
    };
    
    calendar = new FullCalendar.Calendar(el, calendarConfig);
    calendar.render();
  });
})(jQuery);

// Function to close event modal
function closeEventModal() {
  document.getElementById('nds-event-modal').classList.add('hidden');
}
