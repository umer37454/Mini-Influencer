import { Head, Link, useForm, router } from '@inertiajs/react'
import { formatIST } from '@/lib/utils'

interface Profile {
    id: number
    username: string
    platform: string
    status: 'pending' | 'fetching' | 'fetched' | 'failed'
    subscribers_count: number
    videos_count: number
    views_count: number
    last_refreshed_at: string | null
    created_at: string
}

interface Pagination<T> {
    data: T[]
    current_page: number
    last_page: number
    next_page_url: string | null
    prev_page_url: string | null
}

interface Filters {
    search?: string
    status?: string
}

interface Props {
    profiles: Pagination<Profile>
    filters: Filters
}

const statusColors: Record<Profile['status'], string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    fetching: 'bg-blue-100 text-blue-800',
    fetched: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
}

export default function WatchlistIndex({ profiles, filters }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
    })

    function submit(e: React.SyntheticEvent) {
        e.preventDefault()
        post('/watchlist', {
            onSuccess: () => reset(),
        })
    }

    function handleSearch(e: React.ChangeEvent<HTMLInputElement>) {
        router.get(
            '/watchlist',
            { search: e.target.value, status: filters.status },
            { preserveState: true, replace: true }
        )
    }

    function handleStatus(e: React.ChangeEvent<HTMLSelectElement>) {
        router.get(
            '/watchlist',
            { search: filters.search, status: e.target.value },
            { preserveState: true, replace: true }
        )
    }

    return (
        <>
            <Head title="Watchlist" />

            <div className="max-w-6xl mx-auto p-6">
                <h1 className="text-2xl font-bold mb-6">
                    YouTube Watchlist
                </h1>

                {/* Add Channel Form */}
                <form onSubmit={submit} className="flex gap-2 mb-6">
                    <div className="flex-1">
                        <input
                            type="text"
                            placeholder="Enter YouTube handle e.g. @mkbhd"
                            value={data.username}
                            onChange={e => setData('username', e.target.value)}
                            className="w-full border rounded px-3 py-2"
                        />
                        {errors.username && (
                            <p className="text-red-500 text-sm mt-1">
                                {errors.username}
                            </p>
                        )}
                    </div>
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        {processing ? 'Adding...' : 'Add Channel'}
                    </button>
                </form>

                {/* Search + Filter */}
                <div className="flex gap-2 mb-4">
                    <input
                        type="text"
                        placeholder="Search username..."
                        defaultValue={filters.search ?? ''}
                        onChange={handleSearch}
                        className="border rounded px-3 py-2 flex-1"
                    />
                    <select
                        defaultValue={filters.status ?? ''}
                        onChange={handleStatus}
                        className="border rounded px-3 py-2 bg-white text-gray-900"
                    >
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="fetching">Fetching</option>
                        <option value="fetched">Fetched</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                {/* Table */}
                <table className="w-full border rounded overflow-hidden">
                    <thead className="bg-gray-700 text-white">
                        <tr>
                            <th className="text-left p-3">Username</th>
                            <th className="text-left p-3">Status</th>
                            <th className="text-left p-3">Subscribers</th>
                            <th className="text-left p-3">Videos</th>
                            <th className="text-left p-3">Views</th>
                            <th className="text-left p-3">Last Refreshed</th>
                            <th className="text-left p-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {profiles.data.map(profile => (
                            <tr key={profile.id} className="border-t hover:bg-gray-700">
                                <td className="p-3 font-medium">
                                    {profile.username}
                                </td>
                                <td className="p-3">
                                    <span className={`px-2 py-1 rounded text-xs font-medium ${statusColors[profile.status]}`}>
                                        {profile.status}
                                    </span>
                                </td>
                                <td className="p-3">
                                    {profile.subscribers_count.toLocaleString() || 0}
                                </td>
                                <td className="p-3">
                                    {profile.videos_count.toLocaleString() || 0}
                                </td>
                                <td className="p-3">
                                    {profile.views_count.toLocaleString() || 0}
                                </td>
                                <td className="p-3 text-gray-500 text-sm">
                                    {profile.last_refreshed_at ? formatIST(profile.last_refreshed_at) : 'Never'}
                                </td>
                                <td className="p-3">
                                    <Link
                                        href={`/watchlist/${profile.id}`}
                                        className="text-blue-600 hover:underline text-sm"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                        ))}

                        {profiles.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="p-6 text-center text-gray-400"
                                >
                                    No channels found
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>

                {/* Pagination */}
                <div className="flex justify-between items-center mt-4">
                    {profiles.prev_page_url ? (
                        <Link
                            href={profiles.prev_page_url}
                            className="text-blue-600 hover:underline"
                        >
                            ← Previous
                        </Link>
                    ) : <span />}

                    <span className="text-gray-500 text-sm">
                        Page {profiles.current_page} of {profiles.last_page}
                    </span>

                    {profiles.next_page_url ? (
                        <Link
                            href={profiles.next_page_url}
                            className="text-blue-600 hover:underline"
                        >
                            Next →
                        </Link>
                    ) : <span />}
                </div>
            </div>
        </>
    )
}