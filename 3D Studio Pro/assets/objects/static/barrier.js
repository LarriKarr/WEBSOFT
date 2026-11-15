// objects/static/barrier.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    [-0.75, 0.75].forEach(px => {
                        const post = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.07, 0.95, 10), metalMat);
                        post.position.set(px, 0.475, 0);
                        g.add(post);
                    });
                    const rail1 = new THREE.Mesh(new THREE.BoxGeometry(1.7, 0.14, 0.06), new THREE.MeshStandardMaterial({ color: 0xdc2626 }));
                    rail1.position.y = 0.78;
                    const rail2 = new THREE.Mesh(new THREE.BoxGeometry(1.7, 0.14, 0.06), new THREE.MeshStandardMaterial({ color: 0xf8fafc }));
                    rail2.position.y = 0.48;
                    g.add(rail1, rail2);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'barrier',
    category: 'static',
    staticType: 'barrier',
    radius: 0.85,
    build
};
