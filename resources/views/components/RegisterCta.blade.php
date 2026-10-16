<section class="bg-base-100">
  <x-PageContainer class="!pt-0">
    <div class="mx-auto flex max-w-3xl flex-col items-center gap-8 rounded-2xl bg-secondary p-8 text-center text-secondary-content sm:p-12" data-aos="fade-up">
      <div>
        <h3 class="font-heading text-3xl sm:text-4xl">Don't Miss Out!</h3>
        <p class="mx-auto mt-3 max-w-[52ch] text-lg text-secondary-content/85">
          Register your team before November 8th to be part of the most exciting cricket tournament of the year!
        </p>
      </div>
      <x-mary-button label="Register Now" link="{{ route('register') }}" class="btn btn-primary btn-lg rounded-full" />
    </div>
  </x-PageContainer>
</section>
