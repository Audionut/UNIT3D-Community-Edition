<?php
/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.tx
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Http\Livewire;

use App\Models\Category;
use App\Models\History;
use App\Models\Movie;
use App\Models\PrivateMessage;
use App\Models\Torrent;
use App\Models\Tv;
use App\Models\User;
use App\Services\Unit3dAnnounce;
use App\Traits\CastLivewireProperties;
use App\Traits\LivewireSort;
use App\Traits\TorrentMeta;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Closure;
use MarcReichel\IGDBLaravel\Models\Game;

class TorrentSearch extends Component
{
    use CastLivewireProperties;
    use LivewireSort;
    use TorrentMeta;
    use WithPagination;

    // Search Filters
    #TODO: Update URL attributes once Livewire 3 fixes upstream bug. See: https://github.com/livewire/livewire/discussions/7746

    #[Url(history: true)]
    public string $name = '';

    #[Url(history: true)]
    public string $description = '';

    #[Url(history: true)]
    public string $mediainfo = '';

    #[Url(history: true)]
    public string $uploader = '';

    #[Url(history: true)]
    public string $keywords = '';

    #[Url(history: true)]
    public ?int $startYear = null;

    #[Url(history: true)]
    public ?int $endYear = null;

    #[Url(history: true)]
    public ?int $minSize = null;

    #[Url(history: true)]
    public int $minSizeMultiplier = 1;

    #[Url(history: true)]
    public ?int $maxSize = null;

    #[Url(history: true)]
    public int $maxSizeMultiplier = 1;

    /**
     * @var string[]
     */
    #[Url(history: true)]
    public array $categories = [];

    /**
     * @var string[]
     */
    #[Url(history: true)]
    public array $types = [];

    /**
     * @var string[]
     */
    #[Url(history: true)]
    public array $resolutions = [];

    /**
     * @var string[]
     */
    #[Url(history: true)]
    public array $genres = [];

    /**
     * @var string[]
     */
    #[Url(history: true)]
    public array $regions = [];

    /**
     * @var string[]
     */
    #[Url(history: true)]
    public array $distributors = [];

    #[Url(history: true)]
    public string $adult = 'any';

    #[Url(history: true)]
    public ?int $tmdbId = null;

    #[Url(history: true)]
    public string $imdbId = '';

    #[Url(history: true)]
    public ?int $tvdbId = null;

    #[Url(history: true)]
    public ?int $malId = null;

    #[Url(history: true)]
    public ?int $playlistId = null;

    #[Url(history: true)]
    public ?int $collectionId = null;

    #[Url(history: true)]
    public ?int $networkId = null;

    #[Url(history: true)]
    public ?int $companyId = null;

    /**
     * @var string[]
     */
    #[Url(history: true)]
    public array $primaryLanguages = [];

    /**
     * @var string[]
     */
    #[Url(history: true)]
    public array $free = [];

    #[Url(history: true)]
    public bool $doubleup = false;

    #[Url(history: true)]
    public bool $featured = false;

    #[Url(history: true)]
    public bool $refundable = false;

    #[Url(history: true)]
    public bool $stream = false;

    #[Url(history: true)]
    public bool $sd = false;

    #[Url(history: true)]
    public bool $highspeed = false;

    #[Url(history: true)]
    public bool $bookmarked = false;

    #[Url(history: true)]
    public bool $wished = false;

    #[Url(history: true)]
    public bool $internal = false;

    #[Url(history: true)]
    public bool $personalRelease = false;

    #[Url(history: true)]
    public bool $alive = false;

    #[Url(history: true)]
    public bool $dying = false;

    #[Url(history: true)]
    public bool $dead = false;

    #[Url(history: true)]
    public bool $graveyard = false;

    #[Url(history: true)]
    public bool $notDownloaded = false;

    #[Url(history: true)]
    public bool $downloaded = false;

    #[Url(history: true)]
    public bool $seeding = false;

    #[Url(history: true)]
    public bool $leeching = false;

    #[Url(history: true)]
    public bool $incomplete = false;

    #[Url(history: true)]
    public int $perPage = 25;

    #[Url(history: true)]
    public string $sortField = 'bumped_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    #[Url(history: true)]
    public string $view = 'list';

    // Bulk Actions

    /**
     * @var array<int, bool>
     */
    public array $checked = [];

    public bool $selectPage = false;

    public int $category;

    public int $freeleech;

    public int $doubleupload;

    protected $listeners = [
        'destroy'            => 'delete',
        'updateFreeleech'    => 'freeleech',
        'updateDoubleUpload' => 'doubleupload',
        'updateCategory'     => 'category',
    ];

    final public function updatedSelectPage(bool $value): void
    {
        $this->checked = $value ? $this->torrents->pluck('id')->toArray() : [];
    }

    final public function updatedChecked(): void
    {
        $this->selectPage = false;
    }

    final public function updating(string $field, mixed &$value): void
    {
        $this->castLivewireProperties($field, $value);
    }

    final public function updatingName(): void
    {
        $this->resetPage();
    }

    final public function updatedView(): void
    {
        $this->perPage = \in_array($this->view, ['card', 'poster']) ? 24 : 25;
    }

    #[Computed]
    final public function personalFreeleech(): bool
    {
        return cache()->get('personal_freeleech:'.auth()->id()) ?? false;
    }

    /**
     * @return Closure(Builder<Torrent>): Builder<Torrent>
     */
    final public function filters(): Closure
    {
        $user = auth()->user();
        $isRegexAllowed = $user->group->is_modo || $user->group->is_editor;
        $isRegex = fn ($field) => $isRegexAllowed
            && \strlen((string) $field) > 2
            && $field[0] === '/'
            && $field[-1] === '/'
            && @preg_match($field, 'Validate regex') !== false;

        return fn (Builder $query) => $query
            ->when($this->name !== '', fn ($query) => $query->ofName($this->name, $isRegex($this->name)))
            ->when($this->description !== '', fn ($query) => $query->ofDescription($this->description, $isRegex($this->description)))
            ->when($this->mediainfo !== '', fn ($query) => $query->ofMediainfo($this->mediainfo, $isRegex($this->mediainfo)))
            ->when($this->uploader !== '', fn ($query) => $query->ofUploader($this->uploader))
            ->when($this->keywords !== '', fn ($query) => $query->ofKeyword(array_map('trim', explode(',', $this->keywords))))
            ->when($this->startYear !== null, fn ($query) => $query->releasedAfterOrIn($this->startYear))
            ->when($this->endYear !== null, fn ($query) => $query->releasedBeforeOrIn($this->endYear))
            ->when($this->minSize !== null, fn ($query) => $query->ofSizeGreaterOrEqualto($this->minSize * $this->minSizeMultiplier))
            ->when($this->maxSize !== null, fn ($query) => $query->ofSizeLesserOrEqualTo($this->maxSize * $this->maxSizeMultiplier))
            ->when($this->categories !== [], fn ($query) => $query->ofCategory($this->categories))
            ->when($this->types !== [], fn ($query) => $query->ofType($this->types))
            ->when($this->resolutions !== [], fn ($query) => $query->ofResolution($this->resolutions))
            ->when($this->genres !== [], fn ($query) => $query->ofGenre($this->genres))
            ->when($this->regions !== [], fn ($query) => $query->ofRegion($this->regions))
            ->when($this->distributors !== [], fn ($query) => $query->ofDistributor($this->distributors))
            ->when($this->tmdbId !== null, fn ($query) => $query->ofTmdb($this->tmdbId))
            ->when($this->imdbId !== '', fn ($query) => $query->ofImdb((int) (preg_match('/tt0*(?=(\d{7,}))/', $this->imdbId, $matches) ? $matches[1] : $this->imdbId)))
            ->when($this->tvdbId !== null, fn ($query) => $query->ofTvdb($this->tvdbId))
            ->when($this->malId !== null, fn ($query) => $query->ofMal($this->malId))
            ->when($this->playlistId !== null, fn ($query) => $query->ofPlaylist($this->playlistId))
            ->when($this->collectionId !== null, fn ($query) => $query->ofCollection($this->collectionId))
            ->when($this->companyId !== null, fn ($query) => $query->ofCompany($this->companyId))
            ->when($this->networkId !== null, fn ($query) => $query->ofNetwork($this->networkId))
            ->when($this->primaryLanguages !== [], fn ($query) => $query->ofPrimaryLanguage($this->primaryLanguages))
            ->when($this->free !== [], fn ($query) => $query->ofFreeleech($this->free))
            ->when($this->adult === 'include', fn ($query) => $query->ofAdult(true))
            ->when($this->adult === 'exclude', fn ($query) => $query->ofAdult(false))
            ->when($this->doubleup, fn ($query) => $query->doubleup())
            ->when($this->featured, fn ($query) => $query->featured())
            ->when($this->refundable, fn ($query) => $query->refundable())
            ->when($this->stream, fn ($query) => $query->streamOptimized())
            ->when($this->sd, fn ($query) => $query->sd())
            ->when($this->highspeed, fn ($query) => $query->highspeed())
            ->when($this->bookmarked, fn ($query) => $query->bookmarkedBy($user))
            ->when($this->wished, fn ($query) => $query->wishedBy($user))
            ->when($this->internal, fn ($query) => $query->internal())
            ->when($this->personalRelease, fn ($query) => $query->personalRelease())
            ->when($this->alive, fn ($query) => $query->alive())
            ->when($this->dying, fn ($query) => $query->dying())
            ->when($this->dead, fn ($query) => $query->dead())
            ->when($this->graveyard, fn ($query) => $query->graveyard())
            ->when($this->notDownloaded, fn ($query) => $query->notDownloadedBy($user))
            ->when($this->downloaded, fn ($query) => $query->downloadedBy($user))
            ->when($this->seeding, fn ($query) => $query->seededBy($user))
            ->when($this->leeching, fn ($query) => $query->leechedBy($user))
            ->when($this->incomplete, fn ($query) => $query->uncompletedBy($user));
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<Torrent>
     */
    #[Computed]
    final public function torrents(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $user = auth()->user();

        // Whitelist which columns are allowed to be ordered by
        if (!\in_array($this->sortField, [
            'name',
            'size',
            'seeders',
            'leechers',
            'times_completed',
            'created_at',
            'bumped_at',
        ])) {
            $this->reset('sortField');
        }

        $torrents = Torrent::with(['user:id,username,group_id', 'user.group', 'category', 'type', 'resolution'])
            ->withCount([
                'thanks',
                'comments',
                'seeds'   => fn ($query) => $query->where('active', '=', true)->where('visible', '=', true),
                'leeches' => fn ($query) => $query->where('active', '=', true)->where('visible', '=', true),
            ])
            ->withExists([
                'bookmarks'          => fn ($query) => $query->where('user_id', '=', $user->id),
                'freeleechTokens'    => fn ($query) => $query->where('user_id', '=', $user->id),
                'history as seeding' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 1)
                    ->where('seeder', '=', 1),
                'history as leeching' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 1)
                    ->where('seeder', '=', 0),
                'history as not_completed' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 0)
                    ->where('seeder', '=', 0)
                    ->whereNull('completed_at'),
                'history as not_seeding' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 0)
                    ->where(
                        fn ($query) => $query
                            ->where('seeder', '=', 1)
                            ->orWhereNotNull('completed_at')
                    ),
            ])
            ->selectRaw("
                CASE
                    WHEN category_id IN (SELECT `id` from `categories` where `movie_meta` = 1) THEN 'movie'
                    WHEN category_id IN (SELECT `id` from `categories` where `tv_meta` = 1) THEN 'tv'
                    WHEN category_id IN (SELECT `id` from `categories` where `game_meta` = 1) THEN 'game'
                    WHEN category_id IN (SELECT `id` from `categories` where `music_meta` = 1) THEN 'music'
                    WHEN category_id IN (SELECT `id` from `categories` where `no_meta` = 1) THEN 'no'
                END as meta
            ")
            ->where($this->filters())
            ->latest('sticky')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(min($this->perPage, 100));

        // See app/Traits/TorrentMeta.php
        $this->scopeMeta($torrents);

        return $torrents;
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<Torrent>
     */
    #[Computed]
    final public function groupedTorrents()
    {
        $user = auth()->user();

        // Whitelist which columns are allowed to be ordered by
        if (!\in_array($this->sortField, [
            'bumped_at',
            'times_completed',
        ])) {
            $this->reset('sortField');
        }

        $groups = Torrent::query()
            ->select('tmdb')
            ->selectRaw('MAX(sticky) as sticky')
            ->selectRaw('MAX(bumped_at) as bumped_at')
            ->selectRaw('SUM(times_completed) as times_completed')
            ->selectRaw("CASE WHEN category_id IN (SELECT `id` from `categories` where `movie_meta` = 1) THEN 'movie' WHEN category_id IN (SELECT `id` from `categories` where `tv_meta` = 1) THEN 'tv' END as meta")
            ->havingNotNull('meta')
            ->where('tmdb', '!=', 0)
            ->where($this->filters())
            ->groupBy('tmdb', 'meta')
            ->latest('sticky')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(min($this->perPage, 100));

        $movieIds = $groups->getCollection()->where('meta', '=', 'movie')->pluck('tmdb');
        $tvIds = $groups->getCollection()->where('meta', '=', 'tv')->pluck('tmdb');

        $movies = Movie::with('genres', 'directors')->whereIntegerInRaw('id', $movieIds)->get()->keyBy('id');
        $tv = Tv::with('genres', 'creators')->whereIntegerInRaw('id', $tvIds)->get()->keyBy('id');

        $torrents = Torrent::query()
            ->with(['type:id,name,position', 'resolution:id,name,position'])
            ->withCount([
                'seeds'   => fn ($query) => $query->where('active', '=', true)->where('visible', '=', true),
                'leeches' => fn ($query) => $query->where('active', '=', true)->where('visible', '=', true),
            ])
            ->withExists([
                'freeleechTokens'    => fn ($query) => $query->where('user_id', '=', $user->id),
                'bookmarks'          => fn ($query) => $query->where('user_id', '=', $user->id),
                'history as seeding' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 1)
                    ->where('seeder', '=', 1),
                'history as leeching' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 1)
                    ->where('seeder', '=', 0),
                'history as not_completed' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 0)
                    ->where('seeder', '=', 0)
                    ->whereNull('completed_at'),
                'history as not_seeding' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 0)
                    ->where(
                        fn ($query) => $query
                            ->where('seeder', '=', 1)
                            ->orWhereNotNull('completed_at')
                    ),
            ])
            ->select([
                'id',
                'name',
                'info_hash',
                'size',
                'leechers',
                'seeders',
                'times_completed',
                'category_id',
                'user_id',
                'season_number',
                'episode_number',
                'tmdb',
                'stream',
                'free',
                'doubleup',
                'highspeed',
                'featured',
                'sticky',
                'sd',
                'internal',
                'created_at',
                'bumped_at',
                'type_id',
                'resolution_id',
                'personal_release',
            ])
            ->selectRaw("CASE WHEN category_id IN (SELECT `id` from `categories` where `movie_meta` = 1) THEN 'movie' WHEN category_id IN (SELECT `id` from `categories` where `tv_meta` = 1) THEN 'tv' END as meta")
            ->where(
                fn ($query) => $query
                    ->where(
                        fn ($query) => $query
                            ->whereIn('category_id', Category::select('id')->where('movie_meta', '=', 1))
                            ->whereIntegerInRaw('tmdb', $movieIds)
                    )
                    ->orWhere(
                        fn ($query) => $query
                            ->whereIn('category_id', Category::select('id')->where('tv_meta', '=', 1))
                            ->whereIntegerInRaw('tmdb', $tvIds)
                    )
            )
            ->where($this->filters())
            ->get()
            ->groupBy('meta')
            ->map(fn ($movieOrTv, $key) => match ($key) {
                'movie' => $movieOrTv
                    ->groupBy('tmdb')
                    ->map(
                        function ($movie) {
                            $category_id = $movie->first()->category_id;
                            $movie = $movie
                                ->sortBy('type.position')
                                ->values()
                                ->groupBy(fn ($torrent) => $torrent->type->name)
                                ->map(
                                    fn ($torrentsByType) => $torrentsByType
                                        ->sortBy([
                                            ['resolution.position', 'asc'],
                                            ['internal', 'desc'],
                                            ['size', 'desc'],
                                        ])
                                        ->values()
                                );
                            $movie->put('category_id', $category_id);

                            return $movie;
                        }
                    ),
                'tv' => $movieOrTv
                    ->groupBy([
                        fn ($torrent) => $torrent->tmdb,
                    ])
                    ->map(
                        function ($tv) {
                            $category_id = $tv->first()->category_id;
                            $tv = $tv
                                ->groupBy(fn ($torrent) => $torrent->season_number === 0 ? ($torrent->episode_number === 0 ? 'Complete Pack' : 'Specials') : 'Seasons')
                                ->map(fn ($packOrSpecialOrSeasons, $key) => match ($key) {
                                    'Complete Pack' => $packOrSpecialOrSeasons
                                        ->sortBy('type.position')
                                        ->values()
                                        ->groupBy(fn ($torrent) => $torrent->type->name)
                                        ->map(
                                            fn ($torrentsByType) => $torrentsByType
                                                ->sortBy([
                                                    ['resolution.position', 'asc'],
                                                    ['internal', 'desc'],
                                                    ['size', 'desc'],
                                                ])
                                                ->values()
                                        ),
                                    'Specials' => $packOrSpecialOrSeasons
                                        ->groupBy(fn ($torrent) => 'Special '.$torrent->episode_number)
                                        ->sortKeys(SORT_NATURAL)
                                        ->map(
                                            fn ($episode) => $episode
                                                ->sortBy('type.position')
                                                ->values()
                                                ->groupBy(fn ($torrent) => $torrent->type->name)
                                                ->map(
                                                    fn ($torrentsByType) => $torrentsByType
                                                        ->sortBy([
                                                            ['resolution.position', 'asc'],
                                                            ['internal', 'desc'],
                                                            ['size', 'desc'],
                                                        ])
                                                        ->values()
                                                )
                                        ),
                                    'Seasons' => $packOrSpecialOrSeasons
                                        ->groupBy(fn ($torrent) => 'Season '.$torrent->season_number)
                                        ->sortKeys(SORT_NATURAL)
                                        ->map(
                                            fn ($season) => $season
                                                ->groupBy(fn ($torrent) => $torrent->episode_number === 0 ? 'Season Pack' : 'Episodes')
                                                ->map(fn ($packOrEpisodes, $key) => match ($key) {
                                                    'Season Pack' => $packOrEpisodes
                                                        ->sortBy('type.position')
                                                        ->values()
                                                        ->groupBy(fn ($torrent) => $torrent->type->name)
                                                        ->map(
                                                            fn ($torrentsByType) => $torrentsByType
                                                                ->sortBy([
                                                                    ['resolution.position', 'asc'],
                                                                    ['internal', 'desc'],
                                                                    ['size', 'desc'],
                                                                ])
                                                                ->values()
                                                        ),
                                                    'Episodes' => $packOrEpisodes
                                                        ->groupBy(fn ($torrent) => 'Episode '.$torrent->episode_number)
                                                        ->sortKeys(SORT_NATURAL)
                                                        ->map(
                                                            fn ($episode) => $episode
                                                                ->sortBy('type.position')
                                                                ->values()
                                                                ->groupBy(fn ($torrent) => $torrent->type->name)
                                                                ->map(
                                                                    fn ($torrentsBytype) => $torrentsBytype
                                                                        ->sortBy([
                                                                            ['resolution.position', 'asc'],
                                                                            ['internal', 'desc'],
                                                                            ['size', 'desc'],
                                                                        ])
                                                                        ->values()
                                                                )
                                                        ),
                                                    default => abort(500, 'Group found that isn\'t one of: Season Pack, Episodes.'),
                                                })
                                        ),
                                    default => abort(500, 'Group found that isn\'t one of: Complete Pack, Specials, Seasons'),
                                });
                            $tv->put('category_id', $category_id);

                            return $tv;
                        }
                    ),
                default => abort(500, 'Group found that isn\'t one of: movie, tv'),
            });

        $medias = $groups->through(function ($group) use ($torrents, $movies, $tv) {
            $media = collect(['meta' => 'no']);

            switch ($group->meta) {
                case 'movie':
                    $media = $movies[$group->tmdb] ?? collect();
                    $media->meta = 'movie';
                    $media->torrents = $torrents['movie'][$group->tmdb] ?? collect();
                    $media->category_id = $media->torrents->pop();

                    break;
                case 'tv':
                    $media = $tv[$group->tmdb] ?? collect();
                    $media->meta = 'tv';
                    $media->torrents = $torrents['tv'][$group->tmdb] ?? collect();
                    $media->category_id = $media->torrents->pop();

                    break;
            }

            return $media;
        });

        return $medias;
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<Torrent>
     */
    #[Computed]
    final public function groupedPosters()
    {
        // Whitelist which columns are allowed to be ordered by
        if (!\in_array($this->sortField, [
            'bumped_at',
            'times_completed',
        ])) {
            $this->reset('sortField');
        }

        $groups = Torrent::query()
            ->select('tmdb')
            ->selectRaw('MAX(sticky) as sticky')
            ->selectRaw('MAX(bumped_at) as bumped_at')
            ->selectRaw('SUM(times_completed) as times_completed')
            ->selectRaw('MIN(category_id) as category_id')
            ->selectRaw("CASE WHEN category_id IN (SELECT `id` from `categories` where `movie_meta` = 1) THEN 'movie' WHEN category_id IN (SELECT `id` from `categories` where `tv_meta` = 1) THEN 'tv' END as meta")
            ->havingNotNull('meta')
            ->where('tmdb', '!=', 0)
            ->where($this->filters())
            ->groupBy('tmdb', 'meta')
            ->latest('sticky')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(min($this->perPage, 100));

        $movieIds = $groups->getCollection()->where('meta', '=', 'movie')->pluck('tmdb');
        $tvIds = $groups->getCollection()->where('meta', '=', 'tv')->pluck('tmdb');

        $movies = Movie::with('genres', 'directors')->whereIntegerInRaw('id', $movieIds)->get()->keyBy('id');
        $tv = Tv::with('genres', 'creators')->whereIntegerInRaw('id', $tvIds)->get()->keyBy('id');

        $groups = $groups->through(function ($group) use ($movies, $tv) {
            switch ($group->meta) {
                case 'movie':
                    $group->movie = $movies[$group->tmdb] ?? null;

                    break;
                case 'tv':
                    $group->tv = $tv[$group->tmdb] ?? null;

                    break;
            }

            return $group;
        });

        return $groups;
    }

    final public function freeleech(): void
    {
        if (!auth()->user()->group->is_owner) {
            $this->dispatch('error', type: 'error', message: 'Permission Denied!');

            return;
        }

        $torrents = Torrent::whereKey($this->checked)->get();

        $torrents->each(fn ($torrent) => $torrent->update([
            'free' => $this->freeleech,
        ]));

        $this->checked = [];
        $this->selectPage = false;

        $this->dispatch(
            'swal:modal',
            type: 'success',
            message: 'Success!',
            text: 'The torrents you selected now have '.$this->freeleech.'% Freeleech.',
        );
    }

    final public function doubleupload(): void
    {
        if (!auth()->user()->group->is_owner) {
            $this->dispatch('error', type: 'error', message: 'Permission Denied!');

            return;
        }

        $torrents = Torrent::whereKey($this->checked)->get();

        $torrents->each(fn ($torrent) => $torrent->update([
            'doubleup' => $this->doubleupload,
        ]));

        $this->checked = [];
        $this->selectPage = false;

        $this->dispatch(
            'swal:modal',
            type: 'success',
            message: 'Success!',
            text: $this->doubleupload ? 'The torrents you selected are now Double Upload.' : 'The torrents you selected are no longer Double Upload.',
        );
    }

    final public function category(): void
    {
        if (!auth()->user()->group->is_owner) {
            $this->dispatch('error', type: 'error', message: 'Permission Denied!');

            return;
        }

        $torrents = Torrent::whereKey($this->checked)->get();

        $torrents->each(fn ($torrent) => $torrent->update([
            'category_id' => $this->category,
        ]));

        $this->checked = [];
        $this->selectPage = false;

        $category = Category::find($this->category);

        $this->dispatch(
            'swal:modal',
            type: 'success',
            message: 'Success!',
            text: 'The torrents you selected are now in the '.$category->name.' category.',
        );
    }

    final public function delete(): void
    {
        if (!auth()->user()->group->is_modo) {
            $this->dispatch('error', type: 'error', message: 'Permission Denied!');

            return;
        }

        $torrents = Torrent::whereKey($this->checked)->get();
        $names = [];
        $users = [];
        $title = match (true) {
            $this->category->movie_meta => ($movie = Movie::find($this->tmdbId))->title.' ('.$movie->release_date.')',
            $this->category->tv_meta    => ($tv = Tv::find($this->tmdbId))->name.' ('.$tv->first_air_date.')',
            $this->category->game_meta  => ($game = Game::find($this->igdbId))->name.' ('.$game->first_release_date.')',
            default                     => $torrents->pluck('name')->join(', '),
        };

        foreach ($torrents as $torrent) {
            $names[] = $torrent->name;

            foreach (History::where('torrent_id', '=', $torrent->id)->get() as $pm) {
                if (!\in_array($pm->user_id, $users)) {
                    $users[] = $pm->user_id;
                }
            }

            // Reset Requests
            $torrent->requests()->update([
                'torrent_id' => null,
            ]);

            //Remove Torrent related info
            cache()->forget(sprintf('torrent:%s', $torrent->info_hash));

            $torrent->comments()->delete();
            $torrent->peers()->delete();
            $torrent->history()->delete();
            $torrent->hitrun()->delete();
            $torrent->files()->delete();
            $torrent->playlists()->detach();
            $torrent->subtitles()->delete();
            $torrent->resurrections()->delete();
            $torrent->featured()->delete();

            $freeleechTokens = $torrent->freeleechTokens();

            foreach ($freeleechTokens->get() as $freeleechToken) {
                cache()->forget('freeleech_token:'.$freeleechToken->user_id.':'.$torrent->id);
            }

            $freeleechTokens->delete();

            cache()->forget('announce-torrents:by-infohash:'.$torrent->info_hash);

            Unit3dAnnounce::removeTorrent($torrent);

            $torrent->delete();
        }

        foreach ($users as $user) {
            $pmuser = new PrivateMessage();
            $pmuser->sender_id = User::SYSTEM_USER_ID;
            $pmuser->receiver_id = $user;
            $pmuser->subject = 'Bulk Torrents Deleted - '.$title.'! ';
            $pmuser->message = '[b]Attention: [/b] The following torrents have been removed from our site.
            [list]
                [*]'.implode(' [*]', $names).'
            [/list]
            Our system shows that you were either the uploader, a seeder or a leecher on said torrent. We just wanted to let you know you can safely remove it from your client.
                                    [b]Removal Reason: [/b] '.$this->reason.'
                                    [color=red][b]THIS IS AN AUTOMATED SYSTEM MESSAGE, PLEASE DO NOT REPLY![/b][/color]';
            $pmuser->save();
        }

        $this->checked = [];
        $this->selectPage = false;

        $this->dispatch(
            'swal:modal',
            type: 'success',
            message: 'Torrents Deleted Successfully!',
            text: 'A personal message has been sent to all users that have downloaded these torrents.',
        );
    }

    final public function alertConfirm(): void
    {
        if (!auth()->user()->group->is_modo) {
            $this->dispatch('error', type: 'error', message: 'Permission Denied!');

            return;
        }

        $torrents = Torrent::whereKey($this->checked)->pluck('name')->toArray();
        $names = $torrents;
        $this->dispatch(
            'swal:confirm',
            type: 'warning',
            message: 'Are you sure?',
            body: 'You are about to apply changes to following torrents!'.nl2br("\n")
            .nl2br(implode("\n", $names)),
        );
    }

    final public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.torrent-search', [
            'user'              => User::with(['group'])->findOrFail(auth()->id()),
            'personalFreeleech' => $this->personalFreeleech,
            'torrents'          => match ($this->view) {
                'group'  => $this->groupedTorrents,
                'poster' => $this->groupedPosters,
                default  => $this->torrents,
            },
        ]);
    }
}
