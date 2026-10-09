
  <section class="bg-base-100 py-12 sm:py-16 md:py-20 lg:py-24">
    <div class="container mx-auto px-4 sm:px-6 md:px-8">
      <!-- Section Header -->
      <div class="text-center mb-12 md:mb-16" data-aos="fade-up" data-aos-duration="1000">
        <h2 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold font-heading uppercase mb-4">
          <span class="text-base-content">Event </span>
          <span class="text-primary-ink">Timeline</span>
        </h2>
        <p class="text-base-content/80 text-base sm:text-lg md:text-xl max-w-3xl mx-auto">
          Mark your calendars! Here are the key dates for Hit the Grounds 2025.
        </p>
      </div>

      <!-- Timeline -->
      <div class="max-w-4xl mx-auto">
        <ul class="timeline timeline-vertical timeline-snap-icon">

          <!-- Event Announcement -->
          <li>
            <div class="timeline-middle" data-aos="fade-down" data-aos-duration="1000">
              <div class="bg-info text-info-content rounded-full w-14 h-14 shadow-lg flex items-center justify-center">
                <x-mary-icon name="s-calendar" class="w-7 h-7" />
              </div>
            </div>
            <div class="timeline-start md:text-end mb-10 md:mb-0 md:mr-8" data-aos="fade-right" data-aos-duration="2000">
              <time class="font-title text-lg text-info">October 11th, 2025</time>
              <div class="text-xl md:text-2xl font-title text-base-content mt-2">Event Announcement</div>
              <p class="text-base-content/70 text-sm md:text-base mt-2">
                Official announcement of Hit the Grounds 2025
              </p>
            </div>
            <hr class="bg-info" />
          </li>

          <!-- Open Registrations -->
          <li>
            <hr class="bg-info" />
            <div class="timeline-middle" data-aos="fade-down" >
              <div class="bg-success text-success-content rounded-full w-14 h-14 shadow-lg flex items-center justify-center">
                <x-mary-icon name="s-clipboard-document-list" class="w-7 h-7" />
              </div>
            </div>
            <div class="timeline-end md:ml-8 mb-10 md:mb-0" data-aos="fade-left" data-aos-duration="2000">
              <time class="font-title text-lg text-success">October 21st, 2025</time>
              <div class="text-xl md:text-2xl font-title text-base-content mt-2">Open Registrations</div>
              <p class="text-base-content/70 text-sm md:text-base mt-2">
                Team registration opens - secure your spot!
              </p>
            </div>
            <hr class="bg-success" />
          </li>

          <!-- Registration Closing -->
          <li>
            <hr class="bg-success" />
            <div class="timeline-middle" data-aos="fade-up">
              <div class="bg-warning text-warning-content rounded-full w-14 h-14 shadow-lg flex items-center justify-center">
                <x-mary-icon name="s-clock" class="w-7 h-7" />
              </div>
            </div>
            <div class="timeline-start md:text-end mb-10 md:mb-0 md:mr-8" data-aos="fade-right" data-aos-duration="2000">
              <time class="font-title text-lg text-warning">November 12th, 2025</time>
              <div class="text-xl md:text-2xl font-title text-base-content mt-2">Registration Closing</div>
              <p class="text-base-content/70 text-sm md:text-base mt-2">
                Last chance to register your team
              </p>
            </div>
            <hr class="bg-warning" />
          </li>

          <!-- Event Day -->
          <li>
            <hr class="bg-warning" />
            <div class="timeline-middle" data-aos="fade-up">
              <div class="bg-error text-error-content rounded-full w-16 h-16 shadow-xl flex items-center justify-center">
                <x-mary-icon name="s-trophy" class="w-9 h-9" />
              </div>
            </div>
            <div class="timeline-end md:ml-8" data-aos="fade-left" data-aos-duration="2000">
              <time class="font-title text-xl text-error">January 24th, 2026</time>
              <div class="text-2xl md:text-3xl font-title text-error mt-2">Event Day</div>
              <p class="text-base-content/80 text-base md:text-lg mt-2 font-semibold">
                The big day - Let the games begin! 🏏
              </p>
            </div>
          </li>

        </ul>
      </div>

      <!-- Countdown or CTA Card -->
      <div class="mt-16 max-w-3xl mx-auto" data-aos="fade-up" data-aos-duration="1500">
        <div class="card bg-base-200 border-2 border-secondary">
          <div class="card-body text-center p-8">
            <h3 class="text-2xl md:text-3xl font-title text-primary-ink mb-4">
              Don't Miss Out!
            </h3>
            <p class="text-base-content/80 text-base md:text-lg mb-6">
              Register your team before November 12th to be part of the most exciting cricket tournament of the year!
            </p>
            <div class="card-actions justify-center">
              <x-mary-button label="Register Now" link="{{ route('register') }}" class="btn btn-primary btn-lg" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>