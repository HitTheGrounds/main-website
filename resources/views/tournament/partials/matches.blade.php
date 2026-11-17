@if ($filterErrors)
    <div role="alert" class="mt-6 rounded-box border border-base-300 p-6 text-center">
        <p>Select valid match filters and try again.</p>
        <a href="{{ route('tournament.index', ['tab' => 'matches']) }}" class="btn btn-outline mt-4">Reset filters</a>
    </div>
@else
    @php
        $stages = ['G' => 'Group stage', 'QF' => 'Quarter-final', 'SF' => 'Semi-final', 'F' => 'Final'];
        $statuses = ['upcoming' => 'Upcoming', 'live' => 'In progress', 'finished' => 'Finished'];
        $filters = $matchData['filters'];
        $pagination = $matchData['pagination'];
        $hasFilters = $filters['stage'] !== null || $filters['status'] !== null || $filters['group_id'] !== null;
    @endphp
    <details class="tc-filter" @if ($hasFilters || $filters['sort'] !== 'schedule') open @endif>
        <summary>Filter matches <span>{{ $hasFilters ? 'Filters applied' : 'Stage, status, group & order' }}</span></summary>
        <form method="GET" action="{{ route('tournament.index') }}" aria-label="Filter matches" class="tc-form tc-form-matches">
            <input type="hidden" name="tab" value="matches">
            <div>
                <label for="match-stage" class="mb-2 block font-semibold">Stage</label>
                <select id="match-stage" name="stage" class="select w-full">
                    <option value="">All stages</option>
                    @foreach ($stages as $value => $label)
                        <option value="{{ $value }}" @selected($filters['stage'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="match-status" class="mb-2 block font-semibold">Status</label>
                <select id="match-status" name="status" class="select w-full">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="match-group" class="mb-2 block font-semibold">Group</label>
                <select id="match-group" name="group_id" class="select w-full">
                    <option value="">All groups</option>
                    @foreach ($matchData['groups'] as $group)
                        <option value="{{ $group['id'] }}" @selected($filters['group_id'] === $group['id'])>{{ $group['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="match-sort" class="mb-2 block font-semibold">Order</label>
                <select id="match-sort" name="sort" class="select w-full">
                    <option value="schedule" @selected($filters['sort'] === 'schedule')>Schedule</option>
                    <option value="newest" @selected($filters['sort'] === 'newest')>Newest added</option>
                </select>
            </div>
            <div class="tc-form-actions">
                <button type="submit" class="btn btn-primary">Apply filters</button>
                <a href="{{ route('tournament.index', ['tab' => 'matches']) }}" class="btn btn-outline">Reset filters</a>
            </div>
        </form>
    </details>
    <div class="tc-filter-meta">
        <p>{{ $pagination['total'] }} {{ $pagination['total'] === 1 ? 'match' : 'matches' }}{{ $hasFilters ? ' found' : '' }}</p>
        <p>Scores shown after publication</p>
    </div>

    <div class="tc-match-grid" aria-label="Match list">
        @forelse ($matchData['matches'] as $match)
            @include('tournament.partials.match-card')
        @empty
            <p class="rounded-box border border-base-300 p-6 text-center lg:col-span-2">
                @if ($pagination['total'] > 0)
                    No matches on this page. Choose a previous page or reset the filters.
                @elseif ($hasFilters)
                    No public matches match these filters. Try another selection.
                @else
                    No public matches are available yet. Check back after fixtures or results are published.
                @endif
            </p>
        @endforelse
    </div>

    @if ($pagination['last_page'] > 1 || $pagination['page'] > 1)
        <nav aria-label="Match pages" class="tc-pagination">
            @if ($pagination['page'] > 1)
                <a class="btn btn-outline" rel="prev" href="{{ route('tournament.index', ['tab' => 'matches', ...$filters, 'page' => min($pagination['page'] - 1, $pagination['last_page'])]) }}">Previous</a>
            @endif
            <p>Page {{ $pagination['page'] }} of {{ $pagination['last_page'] }}</p>
            @if ($pagination['page'] < $pagination['last_page'])
                <a class="btn btn-outline" rel="next" href="{{ route('tournament.index', ['tab' => 'matches', ...$filters, 'page' => $pagination['page'] + 1]) }}">Next</a>
            @endif
        </nav>
    @endif
@endif
