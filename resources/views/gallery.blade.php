<x-layouts.public>
    <section class="animated-3d-gallery-sec relative overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/hero/0_Background.avif'); z-index: 0;"></div>
        <div class="backdrop-blur-md h-full w-full relative">
            <div class="anim-3d-gallery-wrapper">
                @php
                    $galleryPath = public_path('storage/gallery');
                    $images = [];

                    if (is_dir($galleryPath)) {
                        $files = glob($galleryPath . '/*.avif');
                        sort($files, SORT_NATURAL);
                        $images = array_map(function ($file) {
                            return basename($file);
                        }, $files);
                    }

                    $imageCount = count($images);
                    // Duplicate images to create continuous scroll
                    $imagesRepeated = array_merge($images, $images, $images);
                @endphp

                <div class="rotating-gallery-container">
                    <div class="rotating-gallery-grid" style="--image-count: {{ $imageCount }}">
                        @for ($row = 0; $row < 6; $row++)
                            @php
                                // Rotate the images array for each row to create variation
                                $rowImages = array_merge(
                                    array_slice($imagesRepeated, $row * 2),
                                    array_slice($imagesRepeated, 0, $row * 2)
                                );
                            @endphp
                            <div class="gallery-row">
                                @foreach ($rowImages as $index => $image)
                                    <div class="gallery-cell">
                                        <img src="{{ asset('storage/gallery/' . $image) }}" alt="Gallery Image">
                                    </div>
                                @endforeach
                            </div>
                        @endfor
                    </div>
                </div>
            </div>

            <div class="gallery-title-wrapper absolute z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 pointer-events-none">
                <div class="gallery-title bg-secondary text-secondary-content px-8 py-4 rounded-lg inline-block">
                    <h1 class="font-bold font-title">Hit the Grounds Memories</h1>
                </div>
            </div>
        </div>
    </section>

    <style>
        .animated-3d-gallery-sec {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .gallery-title-wrapper {
            text-align: center;
        }

        .gallery-title h1 {
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .rotating-gallery-container {
            position: relative;
            width: 100%;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .rotating-gallery-grid {
            transform: rotate(-45deg);
            display: flex;
            flex-direction: column;
            gap: 1rem;
            position: absolute;
        }

        .gallery-row {
            display: flex;
            gap: 1rem;
            animation: scrollLeft calc(var(--image-count) * 0.5s) linear infinite;
        }

        .gallery-row:nth-child(even) {
            animation-delay: calc(var(--image-count) * -0.5s);
        }

        .gallery-cell {
            flex-shrink: 0;
            width: auto;
            height: 330px;
            overflow: hidden;
            border-radius: 0.5rem;
        }

        .gallery-cell img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        @keyframes scrollLeft {
            from {
                transform: translateX(0);
            }

            to {
                transform: translateX(calc(-100% / 3));
            }
        }

        @media (max-width: 1024px) {
            .gallery-cell {
                width: auto;
                height: 250px;
            }

            .gallery-title h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            .gallery-cell {
                width: auto;
                height: 150px;
            }

            .gallery-title h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</x-layouts.public>
