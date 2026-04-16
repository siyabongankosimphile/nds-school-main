/**
 * Unified NDS Calendar Component
 * Works for both admin (backend) and frontend (student portal)
 */
(function($){
  'use strict';
  $(document).ready(function(){
    // Detect if we're on admin, staff portal, or student portal
    var isAdmin = document.getElementById('nds-admin-calendar') !== null;
    var isStaff = document.getElementById('nds-staff-calendar') !== null;
    var calendarId = isAdmin ? 'nds-admin-calendar' : (isStaff ? 'nds-staff-calendar' : 'nds-frontend-calendar');
    var modalId = isAdmin ? 'eventDetailsModal' : 'nds-event-modal';
    var modalTitleId = isAdmin ? 'event-details-title' : 'event-modal-title';
    var modalContentId = isAdmin ? 'event-details-content' : 'event-modal-content';
    
    // Get AJAX config - try admin, staff, and frontend localized objects
    var ajaxConfig = window.ndsCalendar || window.ndsStaffCalendar || window.ndsFrontendCalendar;
    if (!ajaxConfig) {
      console.error('Calendar AJAX configuration not found');
      return;
    }
    
    // Determine action name based on context
    var ajaxAction = isAdmin ? 'nds_admin_calendar_events' : (isStaff ? 'nds_staff_calendar_events' : 'nds_public_calendar_events');
    
    console.log(isAdmin ? 'Admin calendar script loaded' : (isStaff ? 'Staff calendar script loaded' : 'Frontend calendar script loaded'));
    var el = document.getElementById(calendarId);
    console.log('Calendar element found:', !!el);
    if (!el || typeof window.FullCalendar === 'undefined') {
      console.error('FullCalendar not loaded or calendar element not found');
      return;
    }

    console.log('Initializing calendar...');
    var calendarConfig = {
      initialView: 'dayGridMonth',
      initialDate: new Date(), // Set to today
      nowIndicator: true, // Show indicator for current time
      headerToolbar: {
        left: 'prev,next',
        center: 'title',
        right: 'timeGridDay,timeGridWeek,dayGridMonth,dayGridYear'
      },
      viewDidMount: function(info) {
        if (info.view.type === 'dayGridYear') {
          // Set calendar height to fit viewport - ensure grid layout works
          var calendarEl = info.el.closest('.fc');
          if (calendarEl) {
            calendarEl.style.height = 'calc(100vh - 200px)';
            calendarEl.style.maxHeight = 'calc(100vh - 200px)';
            // Force grid layout by setting display on the view harness
            var viewHarness = calendarEl.querySelector('.fc-view-harness');
            if (viewHarness) {
              viewHarness.style.display = 'grid';
              viewHarness.style.gridTemplateColumns = 'repeat(4, 1fr)';
              viewHarness.style.gap = '6px';
              viewHarness.style.padding = '6px';
            }
          }
        }
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
          // Compact, classic year view: show mini month calendars with small dots
          dayMaxEvents: 3,
          moreLinkClick: 'popover',
          fixedWeekCount: false,
          showNonCurrentDates: false,
          dayCellDidMount: function(info) {
            // Year view day cells; layout controlled via CSS and viewDidMount
          }
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
        console.log('Loading events for:', info.startStr, 'to', info.endStr);
        // Safely get view type
        var viewType = (info && info.view && info.view.type) ? info.view.type : 'dayGridMonth';
        
        $.ajax({
          url: ajaxConfig.ajaxurl,
          type: 'POST',
          data: {
            action: ajaxAction,
            nonce: ajaxConfig.nonce,
            start: info.startStr,
            end: info.endStr,
            view: viewType // Pass view type to filter events
          },
          success: function(response) {
            console.log('Events loaded:', response);
            if (response && response.success) {
              successCallback(response.data || []);
            } else {
              failureCallback('Failed to load events');
            }
          },
          error: function(xhr, status, error) {
            console.error('AJAX error loading events:', status, error);
            failureCallback('Network error');
          }
        });
      },
      eventClick: function(info) {
        console.log('Event clicked - handler triggered');
        info.jsEvent.preventDefault(); // Prevent default behavior
        
        var event = info.event;
        var props = event.extendedProps || {};
        var type = props.type || 'event';
        
        console.log('Event clicked:', event.title, event.id, 'Type:', type);
        
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
        
        // Show modal - use detected modal IDs
        console.log('Attempting to show modal for:', title);
        var modal = document.getElementById(modalId);
        var titleEl = document.getElementById(modalTitleId);
        var contentEl = document.getElementById(modalContentId);
        
        console.log('Modal elements:', {
            modal: !!modal,
            titleEl: !!titleEl,
            contentEl: !!contentEl,
            modalClasses: modal ? modal.className : 'N/A'
        });
        
        if (modal && titleEl && contentEl) {
            titleEl.textContent = title;
            contentEl.innerHTML = content;
            modal.classList.remove('hidden'); // Use Tailwind class instead of inline style
            document.body.style.overflow = 'hidden'; // Prevent body scrolling
            console.log('Modal should now be visible');
        } else {
            console.error('Modal elements not found - falling back to alert');
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
    
    var calendar = new FullCalendar.Calendar(el, calendarConfig);
    console.log('Calendar created, rendering...');
    calendar.render();
    console.log('Calendar rendered successfully');

    // Listen for view changes to process year view
    calendar.on('viewDidMount', function(info) {
      var viewType = info.view.type;
      var container = info.el;
      if (viewType === 'dayGridYear') {
        // Process year view after a short delay to ensure DOM is ready
        setTimeout(function() {
          // Hide all day cells
          var allDayCells = container.querySelectorAll('.fc-daygrid-day');
          allDayCells.forEach(function(dayCell) {
            dayCell.style.display = 'none';
          });
          
          // Hide day headers
          var dayHeaders = container.querySelectorAll('.fc-col-header-cell, .fc-col-header');
          dayHeaders.forEach(function(header) {
            header.style.display = 'none';
          });
          
          // Find month containers and add month names
          // FullCalendar year view structure: .fc-daygrid-body > .fc-daygrid-month (one per month)
          var monthContainers = container.querySelectorAll('.fc-daygrid-month');
          var processedMonths = new Set();
          
          monthContainers.forEach(function(monthContainer) {
            // Get first day cell in this month to determine month name
            var firstDay = monthContainer.querySelector('.fc-daygrid-day');
            if (firstDay) {
              var dayDate = firstDay.getAttribute('data-date');
              if (dayDate) {
                var date = new Date(dayDate);
                var monthName = date.toLocaleString('default', { month: 'long' });
                var monthKey = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
                
                if (!processedMonths.has(monthKey)) {
                  monthContainer.setAttribute('data-month-name', monthName);
                  monthContainer.setAttribute('data-month-key', monthKey);
                  processedMonths.add(monthKey);
                }
              }
            }
          });
        }, 100);
      }
    });
  });
})(jQuery);

// Function to close event details modal (works for both admin and frontend)
function closeEventDetailsModal() {
  var modal = document.getElementById('eventDetailsModal') || document.getElementById('nds-event-modal');
  if (modal) {
    modal.classList.add('hidden'); // Use Tailwind class instead of inline style
    document.body.style.overflow = ''; // Restore body scrolling
  }
}

// Frontend-specific close function (for backwards compatibility)
function closeEventModal() {
  closeEventDetailsModal();
}
