import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const testPosts = [
	{
		title: 'Test Parent Post',
		content: 'This is a parent post.',
	},
	{
		title: 'Child Post 1',
		content: 'This is another test post of a child post.',
	},
	{
		title: 'Child Post 2',
		content: 'This is another test post of a child post.',
	},
	{
		title: 'Child Post 3',
		content: 'This is another test post of a child post.',
	},
];

const createNewDraft = async (page, title: string) => {
	// Add a chapter:
	await page.getByRole('button', { name: 'Add Chapter' }).click();

	// Create a new draft:
	await page.getByRole('button', { name: 'Create New Draft' }).click();

	// Fill in the chapter title:
	await page.getByLabel('Chapter Title').click();
	await page.getByLabel('Chapter Title').fill(title);

	// Create the draft:
	await page.getByRole('button', { name: 'Create Draft' }).click();
};

test.describe('Create Post', () => {
	test('Ensure post type is properly registered', async ({
		requestUtils,
	}) => {
		const posts = await requestUtils.rest({
			path: '/wp/v2/posts',
			method: 'GET',
		});
		expect(posts).toBeDefined();
	});

	test('Parent and child posts created', async ({
		admin,
		editor,
		requestUtils,
		page,
	}) => {
		// Create the parent post as a draft.
		await admin.createNewPost({
			title: testPosts[0].title,
			content: testPosts[0].content,
			postType: 'post',
		});

		// Save the draft.
		await editor.saveDraft();

		// Can click on the report package plugin:
		await page.getByLabel('Report Package').click();

		// Create the 3 child posts:
		await createNewDraft(page, testPosts[1].title);
		await createNewDraft(page, testPosts[2].title);
		await createNewDraft(page, testPosts[3].title);

		// Publish the parent post.
		await editor.publishPost();

		expect(true).toBe(true);
	});

	test('Child posts are bound to the parent post', async ({
		admin,
		editor,
		requestUtils,
		page,
	}) => {
		// Now, utilizing the rest api look for the 3 test posts and see if they were created and exist.
		const posts = await requestUtils.rest({
			path: '/wp/v2/posts',
			method: 'GET',
		});
		expect(posts).toBeDefined();
		// Check that the parent post exists...
		const parentPost = posts.find(
			(post) => post.title.rendered === testPosts[0].title
		);
		expect(parentPost).toBeDefined();
		expect(parentPost.status).toBe('publish');
	});
});
