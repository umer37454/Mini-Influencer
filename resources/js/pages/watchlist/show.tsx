import { Head, Link, useForm } from '@inertiajs/react'
import { formatIST } from '@/lib/utils'
import React from 'react'

interface Profile {
    id: number
    username: string
    full_name: string | null
    bio: string | null
    profile_picture_url: string | null
    profile_url: string | null
    channel_id: string | null
    platform: string
    status: 'pending' | 'fetching' | 'fetched' | 'failed'
    error_message: string | null
    subscribers_count: number
    videos_count: number
    views_count: number
    last_refreshed_at: string | null
    created_at: string
}

interface Snapshot {
    id: number
    subscribers_count: number
    videos_count: number
    views_count: number
    subscribers_delta: number
    fetched_at: string
}

interface Pagination<T> {
    data: T[]
    current_page: number
    last_page: number
    next_page_url: string | null
    prev_page_url: string | null
}

interface Props {
    profile: Profile
    snapshots: Pagination<Snapshot>
}

const statusColors: Record<Profile['status'], string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    fetching: 'bg-blue-100 text-blue-800',
    fetched: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
}

function DeltaBadge({ delta }: { delta: number }) {
    if (delta > 0) return (
        <span className="text-green-600 font-medium">
            +{delta.toLocaleString()} subscribers
        </span>
    )
    if (delta < 0) return (
        <span className="text-red-600 font-medium">
            {delta.toLocaleString()} subscribers
        </span>
    )

    return <span className="text-gray-400">No change</span>
}

export default function WatchlistShow({ profile, snapshots }: Props) {
    const { post, processing } = useForm({})

    function refetch(e: React.SyntheticEvent) {
        e.preventDefault()
        post(`/watchlist/${profile.id}/refetch`)
    }

    return (
        <>
            <Head title={profile.full_name ?? profile.username} />

            <div className="max-w-4xl mx-auto p-6">

                {/* Back button */}
                <Link
                    href="/watchlist"
                    className="text-blue-600 hover:underline text-sm mb-4 inline-block"
                >
                    ← Back to Watchlist
                </Link>

                {/* Profile Card */}
                <div className="border rounded p-6 mb-6">
                    <div className="flex items-start gap-4">

                        {/* Avatar */}
                        {profile.profile_picture_url ? (
                            <img
                                src={profile.profile_picture_url}
                                alt={profile.full_name ?? profile.username}
                                className="w-20 h-20 rounded-full object-cover"
                            />
                        ) : (
                            <div className="w-20 h-20 rounded-full bg-gray-200 flex items-center justify-center text-2xl font-bold text-gray-500">
                                {profile.username[0]?.toUpperCase() ?? '?'}
                            </div>
                        )}

                        {/* Info */}
                        <div className="flex-1">
                            <div className="flex items-center gap-2 mb-1">
                                <h1 className="text-xl font-bold">
                                    {profile.full_name ?? profile.username}
                                </h1>
                                <span className={`px-2 py-1 rounded text-xs font-medium ${statusColors[profile.status]}`}>
                                    {profile.status}
                                </span>
                            </div>

                            <p className="text-gray-500 text-sm mb-2">
                                @{profile.username}
                            </p>

                            {/* Bio — fallback if null */}
                            <p className="text-gray-600 text-sm mb-3 line-clamp-2">
                                {profile.bio ?? 'No bio available'}
                            </p>

                            {/* Stats */}
                            <div className="flex gap-6">
                                <div>
                                    <p className="text-lg font-bold">
                                        {profile.subscribers_count.toLocaleString()}
                                    </p>
                                    <p className="text-xs text-gray-500">Subscribers</p>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">
                                        {profile.videos_count.toLocaleString()}
                                    </p>
                                    <p className="text-xs text-gray-500">Videos</p>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">
                                        {profile.views_count.toLocaleString()}
                                    </p>
                                    <p className="text-xs text-gray-500">Total Views</p>
                                </div>
                            </div>
                        </div>

                        {/* Refetch button */}
                        <div className="flex flex-col items-end gap-2">
                            <form onSubmit={refetch}>
                                <button
                                    type="submit"
                                    disabled={processing || profile.status === 'fetching'}
                                    className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50 text-sm"
                                >
                                    {processing ? 'Queuing...' : '🔄 Re-fetch now'}
                                </button>
                            </form>

                            {/* Last refreshed — fallback if null */}
                            <p className="text-xs text-gray-400">
                                Last refreshed: {profile.last_refreshed_at
                                    ? formatIST(profile.last_refreshed_at)
                                    : 'Never'
                                }
                            </p>

                            {/* YouTube link — only show if url exists */}
                            {profile.profile_url ? (
                                <a
                                    href={profile.profile_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-xs text-blue-500 hover:underline"
                                >
                                    View on YouTube ↗
                                </a>
                            ) : (
                                <span className="text-xs text-gray-400">
                                    No URL available
                                </span>
                            )}
                        </div>
                    </div>

                    {/* Error message — only show if failed */}
                    {profile.error_message && (
                        <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                            Error: {profile.error_message}
                        </div>
                    )}
                </div>

                {/* Snapshot History */}
                <h2 className="text-lg font-bold mb-3">Snapshot History</h2>

                <table className="w-full border rounded overflow-hidden">
                    <thead className="bg-gray-700 text-white">
                        <tr>
                            <th className="text-left p-3">Fetched At</th>
                            <th className="text-left p-3">Subscribers</th>
                            <th className="text-left p-3">Delta</th>
                            <th className="text-left p-3">Videos</th>
                            <th className="text-left p-3">Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        {snapshots.data.map(snapshot => (
                            <tr key={snapshot.id} className="border-t hover:bg-gray-50">
                                <td className="p-3 text-sm text-gray-500">
                                    {snapshot.fetched_at
                                        ? formatIST(snapshot.fetched_at)
                                        : 'Unknown'
                                    }
                                </td>
                                <td className="p-3">
                                    {snapshot.subscribers_count.toLocaleString()}
                                </td>
                                <td className="p-3">
                                    <DeltaBadge delta={snapshot.subscribers_delta ?? 0} />
                                </td>
                                <td className="p-3">
                                    {snapshot.videos_count.toLocaleString()}
                                </td>
                                <td className="p-3">
                                    {snapshot.views_count.toLocaleString()}
                                </td>
                            </tr>
                        ))}

                        {snapshots.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="p-6 text-center text-gray-400"
                                >
                                    No snapshots yet — fetch the profile first
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>

                {/* Pagination */}
                <div className="flex justify-between items-center mt-4">
                    {snapshots.prev_page_url ? (
                        <Link
                            href={snapshots.prev_page_url}
                            className="text-blue-600 hover:underline"
                        >
                            ← Previous
                        </Link>
                    ) : <span />}

                    <span className="text-gray-500 text-sm">
                        Page {snapshots.current_page} of {snapshots.last_page}
                    </span>

                    {snapshots.next_page_url ? (
                        <Link
                            href={snapshots.next_page_url}
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