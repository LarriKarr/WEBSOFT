// objects/static/billboard.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.14, 0.18, 3.2, 12), metalMat);
                    leg.position.y = 1.6;
                    const frame = new THREE.Mesh(new THREE.BoxGeometry(3.4, 1.7, 0.22), metalMat);
                    frame.position.y = 3.7;
                    const poster = new THREE.Mesh(new THREE.PlaneGeometry(3.2, 1.5), new THREE.MeshBasicMaterial({ color: 0xfacc15 }));
                    poster.position.set(0, 3.7, 0.12);
                    g.add(leg, frame, poster);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'billboard',
    category: 'static',
    staticType: 'billboard',
    radius: 0.85,
    build
};
