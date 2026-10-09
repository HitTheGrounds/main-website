  <section class="bg-base-100 py-16 md:py-24">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

      <header class="mb-14 md:mb-20" data-aos="fade-up">
        <h1 class="font-heading text-[clamp(2.75rem,8vw,5.5rem)]">
          <span class="text-base-content">Event </span>
          <span class="text-primary">Timeline</span>
        </h1>
        <p class="mt-4 max-w-2xl text-lg text-base-content/75">
          Mark your calendars! Here are the key dates for Hit the Grounds 2025.
        </p>
      </header>

      <ol>
        <!-- Event Announcement -->
        <li class="grid gap-3 border-t-2 border-base-content py-8 md:grid-cols-12 md:gap-10" data-aos="fade-up">
          <time class="md:col-span-5 font-heading text-3xl sm:text-4xl text-base-content">October 11th, 2025</time>
          <div class="md:col-span-7">
            <h2 class="font-heading text-2xl md:text-3xl text-base-content">Event Announcement</h2>
            <p class="mt-2 text-base-content/75 text-lg">Official announcement of Hit the Grounds 2025</p>
          </div>
        </li>

        <!-- Open Registrations -->
        <li class="grid gap-3 border-t border-base-content/25 py-8 md:grid-cols-12 md:gap-10" data-aos="fade-up">
          <time class="md:col-span-5 font-heading text-3xl sm:text-4xl text-base-content">October 21st, 2025</time>
          <div class="md:col-span-7">
            <h2 class="font-heading text-2xl md:text-3xl text-base-content">Open Registrations</h2>
            <p class="mt-2 text-base-content/75 text-lg">Team registration opens - secure your spot!</p>
          </div>
        </li>

        <!-- Registration Closing -->
        <li class="grid gap-3 border-t border-base-content/25 py-8 md:grid-cols-12 md:gap-10" data-aos="fade-up">
          <time class="md:col-span-5 font-heading text-3xl sm:text-4xl text-base-content">November 12th, 2025</time>
          <div class="md:col-span-7">
            <h2 class="font-heading text-2xl md:text-3xl text-base-content">Registration Closing</h2>
            <p class="mt-2 text-base-content/75 text-lg">Last chance to register your team</p>
          </div>
        </li>

        <!-- Event Day -->
        <li class="grid gap-3 border-y-2 border-base-content py-10 md:grid-cols-12 md:gap-10" data-aos="fade-up">
          <time class="md:col-span-5 font-heading text-4xl sm:text-5xl text-primary">January 24th, 2026</time>
          <div class="md:col-span-7">
            <h2 class="font-heading text-3xl md:text-4xl text-base-content">Event Day</h2>
            <p class="mt-2 text-lg font-semibold text-base-content/85">The big day - Let the games begin! 🏏</p>
          </div>
        </li>
      </ol>

      <!-- CTA -->
      <div class="mt-16 md:mt-24 rounded-2xl bg-secondary p-8 sm:p-12 text-secondary-content grid gap-8 md:grid-cols-12 md:items-center" data-aos="fade-up">
        <div class="md:col-span-8">
          <h3 class="font-heading text-3xl sm:text-4xl">Don't Miss Out!</h3>
          <p class="mt-3 max-w-[52ch] text-lg text-secondary-content/85">
            Register your team before November 12th to be part of the most exciting cricket tournament of the year!
          </p>
        </div>
        <div class="md:col-span-4 md:text-right">
          <x-mary-button label="Register Now" link="{{ route('register') }}" class="btn btn-primary btn-lg rounded-full" />
        </div>
      </div>
    </div>
  </section>
