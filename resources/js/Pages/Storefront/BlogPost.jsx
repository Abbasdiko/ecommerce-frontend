import { Head, Link } from '@inertiajs/react';

export default function BlogPost({ post, related }) {
    return (
        <main className="marketplace-public-page marketplace-container">
            <Head title={post.title} description={post.excerpt} />
            <article className="marketplace-public-card marketplace-blog-article">
                <p className="marketplace-public-breadcrumb"><Link href="/">Home</Link> · <Link href="/blog">Blog</Link> · {post.category}</p>
                <h1>{post.title}</h1>
                <p className="marketplace-blog-meta">{post.author} · {post.read_minutes} min read{post.published_at ? ` · ${new Date(post.published_at).toLocaleDateString()}` : ''}</p>
                <img className="marketplace-blog-cover" src={post.image} alt="" />
                <div className="marketplace-blog-body">
                    {post.paragraphs.map((paragraph, index) => <p key={index}>{paragraph}</p>)}
                </div>
            </article>
            {related.length > 0 && <section className="marketplace-related-articles">
                <h2>More from {post.category}</h2>
                <div>{related.map((item) => <Link className="marketplace-public-card" key={item.id} href={`/blog/${item.slug}`}>
                    <img src={item.image} alt="" loading="lazy" />
                    <small>{item.category}</small>
                    <strong>{item.title}</strong>
                </Link>)}</div>
            </section>}
        </main>
    );
}
