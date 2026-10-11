
  <section class="bg-base-100 py-12 sm:py-16 md:py-20 lg:py-24">
    <div class="container mx-auto px-4 sm:px-6 md:px-8">
      <!-- Section Header -->
      <div class="text-center mb-12 md:mb-16">
        <h2 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold font-heading uppercase mb-4">
          <span class="text-base-content">Register </span>
          <span class="text-primary-ink">Now</span>
        </h2>
        <p class="text-base-content/80 text-base sm:text-lg md:text-xl max-w-3xl mx-auto">
          Step up to the game and be a part of the action - Register Now !!
        </p>
      </div>

      <!-- Registration Form -->
      <div class="max-w-4xl mx-auto">
        <form class="space-y-8">

          <!-- Company Details Section -->
          <div class="card bg-base-200 shadow-xl border border-base-300">
            <div class="card-body">
              <h3 class="card-title text-2xl md:text-3xl font-title text-primary-ink mb-6">
                <Icon name="mdi:office-building" class="text-3xl" />
                Company Details
              </h3>

              <div class="space-y-6">
                <!-- Company Name -->
                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Company Name *</span>
                  </label>
                  <input
                    type="text"
                    placeholder="Enter your company name"
                    class="input input-bordered w-full"
                    required
                  />
                </div>

                <!-- Payment Status -->
                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Payment Status *</span>
                  </label>
                  <select class="select select-bordered w-full" required>
                    <option disabled selected>Select payment status</option>
                    <option>Payment Completed</option>
                    <option>Payment Pending</option>
                  </select>
                </div>

                <!-- Contact Person's Name -->
                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Contact Person's Name *</span>
                  </label>
                  <input
                    type="text"
                    placeholder="Enter contact person's name"
                    class="input input-bordered w-full"
                    required
                  />
                </div>

                <!-- Contact Number -->
                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Contact Number *</span>
                  </label>
                  <input
                    type="tel"
                    placeholder="07XXXXXXXX (Example: 0771234567)"
                    pattern="07[0-9]{8}"
                    class="input input-bordered w-full"
                    required
                  />
                  <label class="label">
                    <span class="label-text-alt text-xs">Format: 07XXXXXXXX (Example: 0771234567)</span>
                  </label>
                </div>

                <!-- Contact Email -->
                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Contact Email *</span>
                  </label>
                  <input
                    type="email"
                    placeholder="contact@company.com"
                    class="input input-bordered w-full"
                    required
                  />
                  <label class="label">
                    <span class="label-text-alt text-xs">All team cards and details will be sent to this email</span>
                  </label>
                </div>

                <!-- Number of Teams -->
                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Number of Teams *</span>
                  </label>
                  <select class="select select-bordered w-full" required>
                    <option disabled selected>Select number of teams</option>
                    <option>One Team</option>
                    <option>Two Teams</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Team Details Section -->
          <div class="card bg-base-200 shadow-xl border border-base-300">
            <div class="card-body">
              <h3 class="card-title text-2xl md:text-3xl font-title text-primary-ink mb-6">
                <Icon name="mdi:account-group" class="text-3xl" />
                Team Details
              </h3>

              <div class="space-y-6">
                <!-- Team Name -->
                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Team Name *</span>
                  </label>
                  <input
                    type="text"
                    placeholder="Enter your team name"
                    class="input input-bordered w-full"
                    required
                  />
                </div>

                <!-- Captain's Details -->
                <div class="divider font-title text-lg text-base-content/70">Captain's Information</div>

                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Captain's Name *</span>
                  </label>
                  <input
                    type="text"
                    placeholder="Enter captain's name"
                    class="input input-bordered w-full"
                    required
                  />
                </div>

                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Captain's Contact Number *</span>
                  </label>
                  <input
                    type="tel"
                    placeholder="07XXXXXXXX (WhatsApp preferred)"
                    pattern="07[0-9]{8}"
                    class="input input-bordered w-full"
                    required
                  />
                  <label class="label">
                    <span class="label-text-alt text-xs">WhatsApp preferred</span>
                  </label>
                </div>

                <!-- Vice Captain's Details -->
                <div class="divider font-title text-lg text-base-content/70">Vice Captain's Information</div>

                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Vice Captain's Name *</span>
                  </label>
                  <input
                    type="text"
                    placeholder="Enter vice captain's name"
                    class="input input-bordered w-full"
                    required
                  />
                </div>

                <div class="form-control">
                  <label class="label">
                    <span class="label-text text-base font-semibold">Vice Captain's Contact Number *</span>
                  </label>
                  <input
                    type="tel"
                    placeholder="07XXXXXXXX (WhatsApp preferred)"
                    pattern="07[0-9]{8}"
                    class="input input-bordered w-full"
                    required
                  />
                  <label class="label">
                    <span class="label-text-alt text-xs">WhatsApp preferred</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Tournament Rules & Regulations -->
          <div class="card bg-base-200 shadow-xl border border-warning">
            <div class="card-body">
              <h3 class="card-title text-2xl md:text-3xl font-title text-warning mb-4">
                <Icon name="mdi:file-document-check" class="text-3xl" />
                Tournament Rules & Regulations
              </h3>

              <div class="alert alert-warning shadow-lg">
                <Icon name="mdi:information" class="text-2xl" />
                <div>
                  <p class="text-sm md:text-base">
                    Ensure all team members from your company review these Rules & Regulations
                  </p>
                </div>
              </div>

              <a
                href="https://drive.google.com/file/d/1oYv6Fu8jfw3zbJu-oVSeE_LWVcB3a-Jw/view"
                target="_blank"
                class="btn btn-outline btn-warning btn-block mt-4"
              >
                <Icon name="mdi:open-in-new" class="text-xl" />
                View Rules & Regulations
              </a>

              <div class="form-control mt-6">
                <label class="label cursor-pointer justify-start gap-4">
                  <input type="checkbox" class="checkbox checkbox-warning" required />
                  <span class="label-text text-sm md:text-base">
                    I confirm that all team members have reviewed and agree to the Tournament Rules & Regulations *
                  </span>
                </label>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="card bg-base-200 border-2 border-secondary">
            <div class="card-body text-center">
              <button type="submit" class="btn btn-primary btn-lg w-full md:w-auto md:px-16 text-lg">
                <Icon name="mdi:send" class="text-2xl" />
                Submit Registration
              </button>
              <p class="text-base-content/70 text-sm mt-4">
                By submitting this form, you agree to our terms and conditions
              </p>
            </div>
          </div>

        </form>
      </div>
    </div>
  </section>