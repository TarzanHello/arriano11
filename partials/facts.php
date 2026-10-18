<?php // Apartment facts list (line icons) — shared by home and apartment pages ?>
        <ul class="facts">
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 20h18"/><path d="M5 20V10l7-5 7 5v10"/><path d="M9 20v-5h6v5"/></svg>
            <div><strong><?php echo e(t('apartment.section1.outdoor_title')); ?></strong><span><?php echo e(t('apartment.section1.outdoor_text')); ?></span></div>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7v11"/><path d="M21 18v-6a3 3 0 0 0-3-3H8"/><path d="M3 14h18"/><circle cx="6.5" cy="10.5" r="1.5"/></svg>
            <div><strong><?php echo e(t('home.spaces.room1_title')); ?></strong><span><?php echo e(t('booking.labels.guests')); ?>: 1–3</span></div>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3v8a3 3 0 0 0 6 0V3"/><path d="M7 3v18"/><path d="M17 21V3c-2 0-3 2-3 6s1 5 3 5"/></svg>
            <div><strong><?php echo e(t('apartment.section1.kitchen_title')); ?></strong><span><?php echo e(t('apartment.section1.kitchen_text')); ?></span></div>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v3"/><path d="M12 18v3"/><path d="M4.2 7.5l2.6 1.5"/><path d="M17.2 15l2.6 1.5"/><path d="M4.2 16.5l2.6-1.5"/><path d="M17.2 9l2.6-1.5"/><circle cx="12" cy="12" r="3"/></svg>
            <div><strong><?php echo e(t('apartment.section1.ac_title')); ?></strong><span><?php echo e(t('apartment.section1.ac_text')); ?></span></div>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9a15 15 0 0 1 20 0"/><path d="M5.5 12.5a10 10 0 0 1 13 0"/><path d="M9 16a5 5 0 0 1 6 0"/><circle cx="12" cy="19" r="1"/></svg>
            <div><strong><?php echo e(t('apartment.section1.wifi_title')); ?></strong><span><?php echo e(t('apartment.section1.wifi_text')); ?></span></div>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="14" rx="3"/><path d="M5 11h14"/><path d="M8 21l2-4"/><path d="M16 21l-2-4"/></svg>
            <div><strong><?php echo e(t('apartment.section1.metro_title')); ?></strong><span><?php echo e(t('v2.walk')); ?></span></div>
          </li>
        </ul>
