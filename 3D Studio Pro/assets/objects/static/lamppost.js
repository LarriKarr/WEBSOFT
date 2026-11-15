// objects/static/lamppost.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const base = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.22, 0.4, 12), metalMat);
                    base.position.y = 0.2;
                    const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.055, 0.08, 3.4, 12), metalMat);
                    pole.position.y = 1.9;
                    const lantern = new THREE.Mesh(new THREE.SphereGeometry(0.22, 14, 14), new THREE.MeshStandardMaterial({ color: 0xfef08a, emissive: 0xfacc15, emissiveIntensity: 0.85 }));
                    lantern.position.y = 3.7;
                    g.add(base, pole, lantern);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'lamppost',
    category: 'static',
    staticType: 'lamppost',
    radius: 0.45,
    build
};
