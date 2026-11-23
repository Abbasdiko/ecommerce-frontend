import { Head, Link } from '@inertiajs/react';

export default function Blog({ posts }) {
    return (
        <main className="marketplace-public-page marketplace-container">
            <Head title="Blog" />
            <header className="marketplace-public-heading">
                <p><Link href="/">Home</Link> · Stories and guides</p>
                <h1>From our blog</h1>
                <p>Buying guides, useful advice, and news from across the marketplace.</p>
            </header>
            {posts.data.length
                ? <div className="marketplace-blog-list">
                    {posts.data.map((post) => <article className="marketplace-public-card marketplace-blog-list-card" key={post.id}>
                        <Link className="marketplace-blog-list-image" href={`/blog/${post.slug}`}><img src={post.image} alt="" loading="lazy" /></Link>
                        <div>
                            <small>{post.category} · {post.read_minutes} min read</small>
                            <h2><Link href={`/blog/${post.slug}`}>{post.title}</Link></h2>
                            <p>{post.excerpt}</p>
                            <Link className="marketplace-inline-link" href={`/blog/${post.slug}`}>Read article →</Link>
                        </div>
                    </article>)}
                </div>
                : <div className="marketplace-public-card"><p>There are no published articles yet. Check back soon.</p></div>}
            {posts.data.length > 0 && <nav className="marketplace-shop-pagination" aria-label="Blog pages">
                {posts.links.map((link, index) => link.url
                    ? <Link key={index} href={link.url} className={link.active ? 'is-active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} />
                    : <span key={index} dangerouslySetInnerHTML={{ __html: link.label }} />)}
            </nav>}
        </main>
    );
}
